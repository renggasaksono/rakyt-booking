<?php

namespace App\Trips;

use App\Audit\Actor;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\Contracts\DepartureBookings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Departure writes (F-003 AC-17–AC-34, AC-37–AC-39). Shared with F-001 onboarding (4d).
 *
 * Every change runs in a transaction that locks the departure row
 * (SELECT … FOR UPDATE), re-reads seats_taken and writes its audit events —
 * the same lock F-005 takes when it books seats (ADR-0006).
 */
final class DepartureService
{
    public function __construct(
        private DepartureAudit $audit,
        private DepartureBookings $bookings,
    ) {}

    /**
     * @param  array{start_date: string, capacity: int, price_override?: ?int, booking_deadline_preset?: ?string, booking_deadline_at?: ?string}  $input
     */
    public function create(Trip $trip, array $input): TripDeparture
    {
        return DB::transaction(function () use ($trip, $input) {
            $trip = Trip::query()->with('organization')->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            if (! $trip->isScheduled()) {
                throw ValidationException::withMessages(['start_date' => __('trips.errors.request_date_no_departures')]);
            }

            $duration = $trip->duration()
                ?? throw ValidationException::withMessages(['duration' => __('trips.errors.needs_duration')]);

            $organization = $trip->organization;
            $start = $input['start_date'];

            if ($start < TripDeparture::today($organization)) {
                throw ValidationException::withMessages(['start_date' => __('trips.errors.start_date_past')]);
            }

            $preset = DeadlinePreset::from($input['booking_deadline_preset'] ?? $organization->default_booking_deadline_preset);
            $deadline = $this->resolveDeadline($trip, $start, $preset, $input['booking_deadline_at'] ?? null);

            $departure = new TripDeparture([
                'trip_id' => $trip->getKey(),
                'start_date' => $start,
                'end_date' => self::endDate($start, $duration),
                'capacity' => $input['capacity'],
                'price_override' => $input['price_override'] ?? null,
                'booking_deadline_preset' => $preset,
                'booking_deadline_at' => $deadline,
                'status' => DepartureStatus::Open,
            ]);
            $departure->save();
            $departure->setRelation('trip', $trip);

            $this->audit->created($departure);

            return $departure;
        });
    }

    /**
     * Full edit or quick edit. Only the keys present in $changes are changed.
     *
     * @param  array{start_date?: string, capacity?: int, price_override?: ?int, booking_deadline_preset?: string, booking_deadline_at?: ?string, status?: string}  $changes
     */
    public function update(TripDeparture $departure, array $changes): TripDeparture
    {
        return DB::transaction(function () use ($departure, $changes) {
            $locked = $this->lock($departure);
            $trip = $locked->trip;
            $organization = $trip->organization;
            $now = CarbonImmutable::now();

            $this->assertNotPast($locked);

            $before = DepartureAudit::snapshot($locked);
            $errors = [];

            // Start date (AC-18, AC-28).
            $start = $locked->start_date->toDateString();

            if (isset($changes['start_date']) && $changes['start_date'] !== $start) {
                if ($locked->seats_taken > 0) {
                    $errors['start_date'] = __('trips.errors.start_date_has_bookings');
                } elseif ($changes['start_date'] < TripDeparture::today($organization)) {
                    $errors['start_date'] = __('trips.errors.start_date_past');
                } else {
                    $start = $changes['start_date'];
                }
            }

            $startChanged = $start !== $locked->start_date->toDateString();

            // Seats: never below the seats already taken, read under the lock (AC-33, AC-39).
            if (array_key_exists('capacity', $changes) && $changes['capacity'] !== $locked->capacity) {
                if ($changes['capacity'] < $locked->seats_taken) {
                    $errors['capacity'] = __('trips.errors.capacity_floor', ['n' => $locked->seats_taken]);
                } else {
                    $locked->capacity = $changes['capacity'];
                }
            }

            if (array_key_exists('price_override', $changes)) {
                $locked->price_override = $changes['price_override'];
            }

            // Deadline (AC-21–AC-24).
            $preset = isset($changes['booking_deadline_preset'])
                ? DeadlinePreset::from($changes['booking_deadline_preset'])
                : $locked->booking_deadline_preset;
            $presetChanged = $preset !== $locked->booking_deadline_preset;
            $customGiven = $preset === DeadlinePreset::Custom && ! empty($changes['booking_deadline_at']);
            $deadline = $locked->booking_deadline_at;

            try {
                if ($presetChanged || ($startChanged && $preset->isRelative())) {
                    $deadline = $this->resolveDeadline($trip, $start, $preset, $changes['booking_deadline_at'] ?? null);
                } elseif ($customGiven) {
                    $candidate = $this->customDeadline($changes['booking_deadline_at'], $organization);

                    if (! $candidate->equalTo($locked->booking_deadline_at)) {
                        $deadline = $this->resolveDeadline($trip, $start, $preset, $changes['booking_deadline_at']);
                    }
                } elseif ($startChanged && $deadline->greaterThan(DeadlineCalculator::latestAllowed($start, $trip->meeting_time, $organization->timezone))) {
                    // A custom deadline is not moved with the date, only re-validated (AC-24).
                    $errors['booking_deadline_at'] = $this->tooLateMessage($start, $trip, $organization);
                }
            } catch (ValidationException $e) {
                $errors += array_map(fn (array $messages) => $messages[0], $e->errors());
            }

            // Open / closed (AC-34: reopening after the deadline needs a new deadline).
            $status = isset($changes['status']) ? DepartureStatus::from($changes['status']) : $locked->status;

            if ($status === DepartureStatus::Open && ! $locked->isOpen()) {
                if (! $now->lessThan($deadline) && ! isset($errors['booking_deadline_preset']) && ! isset($errors['booking_deadline_at'])) {
                    $errors['booking_deadline_preset'] = __('trips.errors.reopen_needs_deadline', [
                        'deadline' => LocalTime::short($locked->booking_deadline_at, $organization->timezone),
                    ]);
                }

                $locked->status = DepartureStatus::Open;
                $locked->closed_reason = null;
            } elseif ($status === DepartureStatus::Closed && $locked->isOpen()) {
                $locked->status = DepartureStatus::Closed;
                $locked->closed_reason = ClosedReason::Manual;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($startChanged) {
                $locked->start_date = $start;
                $locked->end_date = self::endDate($start, $trip->duration());
            }

            $locked->booking_deadline_preset = $preset;
            $locked->booking_deadline_at = $deadline;
            $locked->save();

            $this->audit->recordChanges($locked, $before);

            return $locked;
        });
    }

    /**
     * Quick edit sheet (1g): the price field holds the effective price; a different
     * value becomes the departure's price override (AC-32).
     *
     * @param  array{price: int, capacity: int, status: string, booking_deadline_preset?: ?string, booking_deadline_at?: ?string}  $input
     */
    public function quickEdit(TripDeparture $departure, array $input): TripDeparture
    {
        $changes = [
            'capacity' => $input['capacity'],
            'status' => $input['status'],
        ];

        if ($input['price'] !== $departure->effectivePrice()) {
            $changes['price_override'] = $input['price'];
        }

        if (! empty($input['booking_deadline_preset'])) {
            $changes['booking_deadline_preset'] = $input['booking_deadline_preset'];
            $changes['booking_deadline_at'] = $input['booking_deadline_at'] ?? null;
        }

        return $this->update($departure, $changes);
    }

    /**
     * Only departures that never had a booking can be deleted (AC-29, Q4).
     */
    public function delete(TripDeparture $departure): void
    {
        DB::transaction(function () use ($departure) {
            $locked = $this->lock($departure);

            $this->assertNotPast($locked);

            if ($this->bookings->hasAnyBooking($locked)) {
                throw ValidationException::withMessages(['departure' => __('trips.errors.departure_has_bookings')]);
            }

            $this->audit->deleted($locked);
            $locked->delete();
        });
    }

    /**
     * System close at the deadline (AC-37). Idempotent: a departure already
     * closed, or whose deadline moved into the future, is left alone.
     */
    public function closeAtDeadline(int $departureId, CarbonImmutable $now, Actor $actor): bool
    {
        return DB::transaction(function () use ($departureId, $now, $actor) {
            $locked = TripDeparture::query()->whereKey($departureId)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isOpen() || $locked->booking_deadline_at->greaterThan($now)) {
                return false;
            }

            $before = DepartureAudit::snapshot($locked);
            $locked->status = DepartureStatus::Closed;
            $locked->closed_reason = ClosedReason::Deadline;
            $locked->save();

            $this->audit->recordChanges($locked, $before, $actor);

            return true;
        });
    }

    /**
     * "Salin dari tanggal sebelumnya" (AC-25): the latest-starting departure's seats,
     * price and deadline preset; a custom deadline becomes the organization default.
     *
     * @return array{capacity: int, price_override: ?int, booking_deadline_preset: string}|null
     */
    public function copyTemplate(Trip $trip): ?array
    {
        $latest = $trip->departures()->orderByDesc('start_date')->orderByDesc('id')->first();

        if ($latest === null) {
            return null;
        }

        $preset = $latest->booking_deadline_preset;

        if ($preset === DeadlinePreset::Custom || ($preset === DeadlinePreset::SameDay && $trip->meeting_time === null)) {
            $preset = DeadlinePreset::from($trip->organization->default_booking_deadline_preset);
        }

        return [
            'capacity' => $latest->capacity,
            'price_override' => $latest->price_override,
            'booking_deadline_preset' => $preset->value,
        ];
    }

    /**
     * Before a trip's meeting time changes: refuse when it would strand a
     * "same day" deadline (no meeting time) or put a custom deadline after the
     * new meeting time (AC-22, AC-24).
     */
    public function assertMeetingTimeChangeAllowed(Trip $trip, ?string $meetingTime): void
    {
        $upcoming = $this->upcoming($trip);

        if ($meetingTime === null) {
            $sameDay = $upcoming->where('booking_deadline_preset', DeadlinePreset::SameDay)->count();

            if ($sameDay > 0) {
                throw ValidationException::withMessages(['meeting_time' => __('trips.errors.meeting_time_same_day', ['n' => $sameDay])]);
            }

            return;
        }

        $tooLate = $upcoming
            ->where('booking_deadline_preset', DeadlinePreset::Custom)
            ->filter(fn (TripDeparture $departure) => $departure->booking_deadline_at->greaterThan(
                DeadlineCalculator::latestAllowed($departure->start_date->toDateString(), $meetingTime, $trip->organization->timezone),
            ))
            ->count();

        if ($tooLate > 0) {
            throw ValidationException::withMessages(['meeting_time' => __('trips.errors.meeting_time_custom', ['n' => $tooLate])]);
        }
    }

    /**
     * After a trip's duration or meeting time changed (inside the trip's transaction):
     * departures without bookings get new end dates (Q3); "same day" deadlines follow
     * the meeting time (AC-24).
     *
     * @return int upcoming departures with bookings whose dates were kept
     */
    public function syncWithTrip(Trip $trip, bool $durationChanged, bool $meetingTimeChanged): int
    {
        $kept = 0;
        $duration = $trip->duration();

        foreach ($this->upcoming($trip, lock: true) as $departure) {
            $before = DepartureAudit::snapshot($departure);

            if ($durationChanged && $duration !== null) {
                if ($departure->seats_taken > 0) {
                    $kept++;
                } else {
                    $departure->end_date = self::endDate($departure->start_date->toDateString(), $duration);
                }
            }

            if ($meetingTimeChanged && $departure->booking_deadline_preset === DeadlinePreset::SameDay && $trip->meeting_time !== null) {
                $departure->booking_deadline_at = DeadlineCalculator::compute(
                    DeadlinePreset::SameDay,
                    $departure->start_date->toDateString(),
                    $trip->meeting_time,
                    $trip->organization->timezone,
                );
            }

            if ($departure->isDirty()) {
                $departure->save();
                $this->audit->recordChanges($departure, $before);
            }
        }

        return $kept;
    }

    /**
     * Whether any upcoming departure of the trip already has seats taken
     * (booking mode is locked then, Data & rules).
     */
    public function hasUpcomingWithSeatsTaken(Trip $trip): bool
    {
        return $this->upcoming($trip)->contains(fn (TripDeparture $departure) => $departure->seats_taken > 0);
    }

    /**
     * Compute and validate a deadline for a start date (AC-22, AC-23).
     */
    public function resolveDeadline(Trip $trip, string $startDate, DeadlinePreset $preset, ?string $customLocal): CarbonImmutable
    {
        $organization = $trip->organization;
        $timezone = $organization->timezone;

        if ($preset === DeadlinePreset::SameDay && $trip->meeting_time === null) {
            throw ValidationException::withMessages(['booking_deadline_preset' => __('trips.errors.deadline_same_day_unavailable')]);
        }

        if ($preset === DeadlinePreset::Custom && empty($customLocal)) {
            throw ValidationException::withMessages(['booking_deadline_at' => __('trips.errors.deadline_custom_required')]);
        }

        $custom = $preset === DeadlinePreset::Custom ? $this->customDeadline($customLocal, $organization) : null;
        $deadline = DeadlineCalculator::compute($preset, $startDate, $trip->meeting_time, $timezone, $custom);
        $field = $preset === DeadlinePreset::Custom ? 'booking_deadline_at' : 'booking_deadline_preset';

        if (! CarbonImmutable::now()->lessThan($deadline)) {
            throw ValidationException::withMessages([$field => $preset === DeadlinePreset::Custom
                ? __('trips.errors.deadline_custom_past')
                : __('trips.errors.deadline_preset_past', ['preset' => __("trips.deadline.short.{$preset->value}")])]);
        }

        if ($deadline->greaterThan(DeadlineCalculator::latestAllowed($startDate, $trip->meeting_time, $timezone))) {
            throw ValidationException::withMessages([$field => $this->tooLateMessage($startDate, $trip, $organization)]);
        }

        return $deadline;
    }

    public static function endDate(string $startDate, ?Duration $duration): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $startDate)->addDays(max(0, ($duration?->days ?? 1) - 1))->toDateString();
    }

    private function customDeadline(string $value, Organization $organization): CarbonImmutable
    {
        return LocalTime::parse($value, $organization->timezone)
            ?? throw ValidationException::withMessages(['booking_deadline_at' => __('trips.errors.deadline_custom_required')]);
    }

    private function tooLateMessage(string $startDate, Trip $trip, Organization $organization): string
    {
        return __('trips.errors.deadline_too_late', [
            'limit' => LocalTime::short(DeadlineCalculator::latestAllowed($startDate, $trip->meeting_time, $organization->timezone), $organization->timezone),
        ]);
    }

    private function lock(TripDeparture $departure): TripDeparture
    {
        $locked = TripDeparture::query()->whereKey($departure->getKey())->lockForUpdate()->firstOrFail();
        $locked->setRelation('trip', Trip::query()->with('organization')->whereKey($locked->trip_id)->firstOrFail());

        return $locked;
    }

    private function assertNotPast(TripDeparture $departure): void
    {
        if ($departure->hasStarted()) {
            throw ValidationException::withMessages(['departure' => __('trips.errors.departure_past_read_only')]);
        }
    }

    /**
     * Departures starting today or later, sorted by date.
     *
     * @return Collection<int, TripDeparture>
     */
    private function upcoming(Trip $trip, bool $lock = false): Collection
    {
        $departures = $trip->departures()
            ->whereDate('start_date', '>=', TripDeparture::today($trip->organization))
            ->orderBy('start_date')
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        return $departures->each(fn (TripDeparture $departure) => $departure->setRelation('trip', $trip));
    }
}
