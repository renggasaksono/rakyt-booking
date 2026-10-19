<?php

namespace App\Bookings;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
 * change (organization, then departures), so the two never deadlock.
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
