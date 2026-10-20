<?php

namespace App\Bookings;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Media\MediaPath;
use App\Media\PrivateMedia;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Payments\PaymentDecision;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * Every booking change that moves seats (F-005, ADR-0006, ADR-0026 §3). The
 * status change, the seat change on trip_departures.seats_taken and the
 * audit event happen in one transaction, so the invariant
 * seats_taken = Σ participants of holding bookings always holds (AC-23).
 *
 * Lock order is fixed — organization row (only when a reference number is
 * taken) → departure row → booking rows — the same order as the time-zone
 * change (organization, then departures), so the two never deadlock. The
 * seller's cancellation (F-007) takes no organization lock: no reference.
 */
final class BookingService
{
    /** Access token length (ADR-0027 §2: ≈ 190 bits, URL-safe). */
    public const TOKEN_LENGTH = 32;

    public function __construct(
        private CurrentOrganization $current,
        private AuditLog $audit,
    ) {}

    /**
     * Create a booking for the current organization's departure (AC-12–AC-17,
     * AC-26). Throws BookingRefused when the departure is no longer bookable,
     * the price changed or the seats ran out; the whole transaction — counter
     * and inline releases included — is rolled back then.
     *
     * A submission key that was already used returns that booking (AC-16).
     *
     * @param  array{name: string, phone: string, participants: int, notes: ?string, submission_key: string, price: int}  $data  validated
     */
    public function create(TripDeparture $departure, array $data): Booking
    {
        try {
            return DB::transaction(fn () => $this->createLocked($departure, $data));
        } catch (UniqueConstraintViolationException $e) {
            // Backstop: the organization lock already serialises same-key submits.
            return $this->replay($departure, $data['submission_key']) ?? throw $e;
        }
    }

    /**
     * Release the departure's expired holds now (ADR-0026 §7), e.g. before the
     * booking form counts seats while the scheduler is late. A no-op without
     * expired holds.
     *
     * @return int bookings released
     */
    public function releaseExpiredOn(TripDeparture $departure, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        if (! Booking::query()->where('trip_departure_id', $departure->getKey())->expiredHolds($now)->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($departure, $now) {
            $locked = TripDeparture::query()->whereKey($departure->getKey())->lockForUpdate()->firstOrFail();

            return $this->releaseExpiredLocked($locked, $now);
        });
    }

    /**
     * Release one expired hold (the expiry job, AC-24). Idempotent: a booking
     * that is no longer an expired hold — released by another run, inline, or
     * moved to needs_review — is left alone.
     */
    public function releaseExpiredHold(int $bookingId, CarbonImmutable $now, Actor $actor): bool
    {
        return DB::transaction(function () use ($bookingId, $now, $actor) {
            $departureId = Booking::query()->whereKey($bookingId)->value('trip_departure_id');

            if ($departureId === null) {
                return false;
            }

            $locked = TripDeparture::query()->whereKey($departureId)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($bookingId)->expiredHolds($now)->lockForUpdate()->first();

            if ($booking === null) {
                return false;
            }

            $this->release($locked, $booking, $actor);

            return true;
        });
    }

    /**
     * The seller cancels a booking (F-007 AC-29–AC-33): departure row, then
     * booking row, re-read under the locks; only a booking still in a holding
     * status changes, so the expiry job, a second tab or a double submit can
     * never return its seats twice (AC-30, AC-31). Any departure date (AC-33);
     * a closed departure stays closed (AC-32). The hold time is unchanged.
     *
     * @return bool whether the booking was cancelled now
     */
    public function cancelBySeller(Booking $booking, User $seller): bool
    {
        return DB::transaction(function () use ($booking, $seller) {
            $locked = TripDeparture::query()->whereKey($booking->trip_departure_id)->lockForUpdate()->firstOrFail();
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if ($fresh === null || ! $fresh->status->holdsSeats()) {
                return false;
            }

            $before = $fresh->status;
            $fresh->status = BookingStatus::Cancelled;
            $fresh->cancel_reason = CancelReason::Seller;
            $fresh->save();

            $this->changeSeats($locked, -$fresh->participants);

            $this->audit->record(AuditAction::BookingCancelled, $fresh,
                before: ['status' => $before->value, 'cancel_reason' => null],
                after: ['status' => BookingStatus::Cancelled->value, 'cancel_reason' => CancelReason::Seller->value],
                actor: Actor::user($seller),
            );

            $booking->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    /**
     * The customer uploads payment evidence (F-006 AC-12, AC-13, AC-15). The
     * file is written to the private disk first (outside the lock), then under
     * the departure → booking lock the hold is re-checked against
     * Booking::holdIsActive(now): an awaiting_payment booking with an active
     * hold moves to needs_review (the hold clock stops, ADR-0006); a
     * needs_review booking keeps its status and its evidence is replaced. Any
     * other status, or an expired hold, is refused — nothing is stored and the
     * booking is unchanged (the freshly written file is deleted).
     *
     * @return ?PaymentRecord the record, or null when the upload is refused
     */
    public function uploadEvidence(Booking $booking, UploadedFile $file, ?CarbonImmutable $now = null): ?PaymentRecord
    {
        $now ??= CarbonImmutable::now();
        $organization = $this->current->get() ?? throw new LogicException('Evidence upload needs a current organization.');

        $extension = $file->extension() ?: 'jpg';
        $path = MediaPath::for($organization, 'evidence/'.$booking->public_id, $extension);
        Storage::disk(PrivateMedia::disk())->put($path, $file->get());

        $result = DB::transaction(function () use ($booking, $path, $now) {
            $locked = TripDeparture::query()->whereKey($booking->trip_departure_id)->lockForUpdate()->firstOrFail();
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            // AC-13 / AC-15: only an active awaiting_payment hold or a needs_review booking.
            if ($fresh === null || ! $this->acceptsEvidence($fresh, $now)) {
                return null;
            }

            $record = PaymentRecord::query()->where('booking_id', $fresh->getKey())->lockForUpdate()->first() ?? new PaymentRecord;
            $previous = $record->evidence_path;
            $record->forceFill([
                'booking_id' => $fresh->getKey(),
                'amount_due' => $fresh->total_amount,
                'evidence_path' => $path,
                'evidence_uploaded_at' => $now,
            ]);
            $record->save();

            if ($fresh->status === BookingStatus::AwaitingPayment) {
                $fresh->status = BookingStatus::NeedsReview;
                $fresh->save();
            }

            // No file bytes, no path (AC-12, AC-30); the path never enters audit.
            $this->audit->record(AuditAction::PaymentEvidenceUploaded, $fresh,
                after: ['status' => $fresh->status->value],
                actor: Actor::customer($fresh->getKey()),
            );

            $booking->setRawAttributes($fresh->getAttributes(), true);

            return ['record' => $record, 'previous' => $previous];
        });

        if ($result === null) {
            Storage::disk(PrivateMedia::disk())->delete($path);

            return null;
        }

        if ($result['previous'] !== null && $result['previous'] !== $path) {
            Storage::disk(PrivateMedia::disk())->delete($result['previous']);
        }

        return $result['record'];
    }

    /**
     * The seller confirms a payment (F-006 AC-17): needs_review → paid under the
     * departure → booking lock, re-reading the status. Seats are unchanged (the
     * seat was already held). Idempotent: a booking that is no longer
     * needs_review (confirmed/rejected in another tab, cancelled) is left alone
     * and false is returned (AC-20).
     */
    public function confirmPayment(Booking $booking, User $seller): bool
    {
        return $this->verify($booking, $seller, BookingStatus::Paid, PaymentDecision::Confirmed, null);
    }

    /**
     * The seller rejects a payment (F-006 AC-18): needs_review → rejected under
     * the lock. `rejected` is terminal and non-holding (ADR-0030 §2), so the
     * booking's participants are returned to the departure (seats back on sale).
     * Idempotent like confirmPayment (AC-20).
     */
    public function rejectPayment(Booking $booking, User $seller, ?string $reason = null): bool
    {
        return $this->verify($booking, $seller, BookingStatus::Rejected, PaymentDecision::Rejected, $reason);
    }

    /**
     * Whether an upload is accepted now (AC-12, AC-13, AC-15).
     */
    private function acceptsEvidence(Booking $booking, CarbonImmutable $now): bool
    {
        return ($booking->status === BookingStatus::AwaitingPayment && $booking->holdIsActive($now))
            || $booking->status === BookingStatus::NeedsReview;
    }

    /**
     * The shared confirm / reject transition (AC-17, AC-18, AC-20). Exactly one
     * transition happens per booking; the record carries the verdict.
     */
    private function verify(Booking $booking, User $seller, BookingStatus $to, PaymentDecision $decision, ?string $reason): bool
    {
        return DB::transaction(function () use ($booking, $seller, $to, $decision, $reason) {
            $locked = TripDeparture::query()->whereKey($booking->trip_departure_id)->lockForUpdate()->firstOrFail();
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== BookingStatus::NeedsReview) {
                return false;
            }

            $record = PaymentRecord::query()->where('booking_id', $fresh->getKey())->lockForUpdate()->firstOrFail();
            $before = $fresh->status;

            $fresh->status = $to;
            $fresh->save();

            // rejected leaves the holding set: its seats go back on sale (ADR-0030 §2).
            if (! $to->holdsSeats()) {
                $this->changeSeats($locked, -$fresh->participants);
            }

            $record->forceFill([
                'verified_by' => $seller->getKey(),
                'verified_at' => CarbonImmutable::now(),
                'decision' => $decision,
                'reject_reason' => $decision === PaymentDecision::Rejected ? $reason : null,
            ]);
            $record->save();

            $action = $decision === PaymentDecision::Confirmed ? AuditAction::PaymentConfirmed : AuditAction::PaymentRejected;
            $after = ['status' => $to->value];
            if ($decision === PaymentDecision::Rejected && $reason !== null) {
                $after['reject_reason'] = $reason;
            }
            $this->audit->record($action, $fresh, before: ['status' => $before->value], after: $after, actor: Actor::user($seller));

            $booking->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    /**
     * @param  array{name: string, phone: string, participants: int, notes: ?string, submission_key: string, price: int}  $data
     */
    private function createLocked(TripDeparture $departure, array $data): Booking
    {
        // 1–2. Organization row: serialises this seller's bookings and its counter.
        $organization = Organization::query()->whereKey($this->current->id())->lockForUpdate()->firstOrFail();

        $existing = $this->replay($departure, $data['submission_key']);

        if ($existing !== null) {
            return $existing;
        }

        $number = $organization->last_booking_number + 1;
        Organization::query()->whereKey($organization->getKey())->update(['last_booking_number' => $number]);

        // 3. Departure row, read again under the lock; expired holds of this departure go first.
        $locked = TripDeparture::query()->whereKey($departure->getKey())->lockForUpdate()->firstOrFail();
        // The clock is read under the locks: the deadline is exact for the submit that holds them (AC-15).
        $now = CarbonImmutable::now();
        $trip = Trip::query()->whereKey($locked->trip_id)->firstOrFail();
        $trip->setRelation('organization', $organization);
        $locked->setRelation('trip', $trip);

        $this->releaseExpiredLocked($locked, $now);

        // 4. Bookable now (AC-15), the price the customer saw (AC-13), enough seats (AC-14).
        if (! $locked->isBookable($now) || $locked->effectivePrice() === null) {
            throw BookingRefused::unavailable();
        }

        if ($locked->effectivePrice() !== $data['price']) {
            throw BookingRefused::priceChanged($locked->effectivePrice());
        }

        $participants = $data['participants'];

        if ($participants > $locked->seatsLeft()) {
            throw BookingRefused::notEnoughSeats($locked->seatsLeft());
        }

        // 5. The booking, its seats and its audit event.
        $token = Str::random(self::TOKEN_LENGTH);
        $booking = new Booking([
            'trip_departure_id' => $locked->getKey(),
            'customer_name' => $data['name'],
            'customer_phone' => $data['phone'],
            'participants' => $participants,
            'notes' => $data['notes'],
        ]);
        $booking->forceFill([
            'reference' => self::reference($organization, $locked, $number),
            'access_token' => $token,
            'access_token_hash' => Booking::hashToken($token),
            'submission_key' => $data['submission_key'],
            'unit_price' => $locked->effectivePrice(),
            'total_amount' => $participants * $locked->effectivePrice(),
            'status' => BookingStatus::AwaitingPayment,
            'hold_expires_at' => $now->addMinutes(self::holdMinutes()),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $booking->save();

        $this->changeSeats($locked, $participants);

        $this->audit->record(AuditAction::BookingCreated, $booking, after: [
            'reference' => $booking->reference,
            'trip_departure_id' => $locked->getKey(),
            'participants' => $participants,
            'unit_price' => $booking->unit_price,
            'total_amount' => $booking->total_amount,
            'status' => $booking->status->value,
            'hold_expires_at' => $booking->hold_expires_at->utc()->toIso8601String(),
        ], actor: Actor::customer($booking->getKey()));

        return $booking;
    }

    /**
     * The booking an earlier submit with this key created (AC-16). A key is
     * only replayed for the departure it was used on; anything else is refused
     * rather than handing out another booking's link (ADR-0028 §3).
     */
    private function replay(TripDeparture $departure, string $key): ?Booking
    {
        $existing = Booking::query()->where('submission_key', $key)->first();

        if ($existing !== null && $existing->trip_departure_id !== $departure->getKey()) {
            throw BookingRefused::unavailable();
        }

        return $existing;
    }

    /**
     * Release every expired hold of a departure locked by the caller.
     */
    private function releaseExpiredLocked(TripDeparture $locked, CarbonImmutable $now): int
    {
        $expired = Booking::query()
            ->where('trip_departure_id', $locked->getKey())
            ->expiredHolds($now)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Inline releases are audited like the job's (F-005 Data & rules → Audit).
        $actor = Actor::system(ReleaseExpiredHolds::NAME);

        foreach ($expired as $booking) {
            $this->release($locked, $booking, $actor);
        }

        return $expired->count();
    }

    /**
     * awaiting_payment → cancelled (hold_expired): the seats come back (AC-24, AC-26).
     */
    private function release(TripDeparture $locked, Booking $booking, Actor $actor): void
    {
        $booking->status = BookingStatus::Cancelled;
        $booking->cancel_reason = CancelReason::HoldExpired;
        $booking->save();

        $this->changeSeats($locked, -$booking->participants);

        $this->audit->record(AuditAction::BookingHoldExpired, $booking,
            before: ['status' => BookingStatus::AwaitingPayment->value, 'cancel_reason' => null],
            after: ['status' => BookingStatus::Cancelled->value, 'cancel_reason' => CancelReason::HoldExpired->value],
            actor: $actor,
        );
    }

    /**
     * The only place seats_taken moves for bookings. Taking seats is also
     * conditional on capacity — a second guard after the check under the
     * lock; the CHECK constraint is the last one (ADR-0006).
     */
    private function changeSeats(TripDeparture $locked, int $delta): void
    {
        $query = TripDeparture::query()->whereKey($locked->getKey());

        $rows = $delta >= 0
            ? $query->whereRaw('seats_taken + ? <= capacity', [$delta])->increment('seats_taken', $delta)
            : $query->whereRaw('seats_taken + ? >= 0', [$delta])->decrement('seats_taken', -$delta);

        if ($rows !== 1) {
            throw new LogicException('Seat change refused for departure '.$locked->getKey().'.');
        }

        $locked->seats_taken += $delta;
        $locked->syncOriginalAttribute('seats_taken');
    }

    /**
     * RKB-<booking code>-<MMDD of the departure start>-<NNNN> (AC-17, ADR-0026 §5).
     */
    public static function reference(Organization $organization, TripDeparture $departure, int $number): string
    {
        return sprintf('RKB-%s-%s-%04d', $organization->booking_code, $departure->start_date->format('md'), $number);
    }

    public static function holdMinutes(): int
    {
        return (int) config('rakyt.booking.hold_minutes');
    }
}
