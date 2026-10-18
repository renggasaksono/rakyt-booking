<?php

namespace App\Trips;

use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripPhoto;
use App\Tenancy\Rules\UniqueInCurrentOrganization;
use App\Trips\Contracts\DepartureBookings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Trip writes (F-003 AC-1–AC-4, AC-11–AC-16). Shared with F-001 onboarding (4c).
 */
final class TripService
{
    public function __construct(
        private AuditLog $audit,
        private DepartureService $departures,
        private DepartureAudit $departureAudit,
        private DepartureBookings $bookings,
        private PublishChecklist $checklist,
    ) {}

    /**
     * A new draft. Only the name is required (AC-2); the type preset fills
     * booking mode and duration unless they are given (AC-1).
     *
     * @param  array<string, mixed>  $data  normalized and validated (TripRules)
     */
    public function create(array $data): Trip
    {
        $type = TripType::from($data['type'] ?? TripType::OpenTrip->value);
        $preset = $type->preset();

        $data['type'] = $type;
        $data['booking_mode'] ??= $preset['booking_mode'];

        if (! array_key_exists('duration_label', $data) && $preset['duration'] !== null) {
            $data = [...$data, ...$preset['duration']->toColumns()];
        }

        return DB::transaction(function () use ($data) {
            $trip = new Trip($data);
            $trip->slug = $this->uniqueSlug($data['name']);
            $trip->status = TripStatus::Draft;
            $trip->save();

            if ($trip->base_price !== null) {
                $this->audit->record(AuditAction::TripBasePriceChanged, $trip, ['base_price' => null], ['base_price' => $trip->base_price]);
            }

            return $trip;
        });
    }

    /**
     * Save trip fields: a draft autosave (partial) or "Simpan perubahan".
     *
     * @param  array<string, mixed>  $data  normalized and validated (TripRules)
     * @return list<string> notices for the seller
     */
    public function update(Trip $trip, array $data): array
    {
        return DB::transaction(function () use ($trip, $data) {
            $locked = Trip::query()->with('organization')->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            $this->assertPublishedStaysComplete($locked, $data);

            $locked->fill($data);

            if ($locked->isDirty('booking_mode') && $this->departures->hasUpcomingWithSeatsTaken($locked)) {
                throw ValidationException::withMessages(['booking_mode' => __('trips.errors.booking_mode_locked')]);
            }

            $durationChanged = $locked->isDirty(['duration_label', 'duration_days', 'duration_nights']);

            if ($durationChanged && $locked->duration() === null && $locked->isScheduled() && $locked->departures()->exists()) {
                throw ValidationException::withMessages(['duration_label' => __('trips.errors.duration_required_with_departures')]);
            }

            $meetingTimeChanged = $locked->isDirty('meeting_time') && $locked->getOriginal('meeting_time') !== $locked->meeting_time;

            if ($meetingTimeChanged) {
                $this->departures->assertMeetingTimeChangeAllowed($locked, $locked->meeting_time);
            }

            $locked->save();

            if ($locked->wasChanged('base_price')) {
                $this->audit->record(
                    AuditAction::TripBasePriceChanged,
                    $locked,
                    ['base_price' => $locked->getPrevious()['base_price'] ?? null],
                    ['base_price' => $locked->base_price],
                );
            }

            $synced = ($durationChanged || $meetingTimeChanged)
                ? $this->departures->syncWithTrip($locked, $durationChanged, $meetingTimeChanged)
                : ['kept' => 0, 'closed' => 0];

            $trip->setRawAttributes($locked->getAttributes(), true);

            return array_values(array_filter([
                $synced['kept'] > 0 ? __('trips.notices.booked_unchanged', ['n' => $synced['kept']]) : null,
                $synced['closed'] > 0 ? __('trips.notices.closed_by_meeting_time', ['n' => $synced['closed']]) : null,
            ]));
        });
    }

    /**
     * AC-11, AC-12. Refused server-side while the checklist is incomplete.
     */
    public function publish(Trip $trip): void
    {
        DB::transaction(function () use ($trip) {
            $locked = Trip::query()->with('organization')->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isPublished()) {
                return;
            }

            $missing = $this->checklist->missing($locked);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'publish' => __('trips.errors.publish_incomplete', ['items' => implode(', ', PublishChecklist::labels($missing))]),
                ]);
            }

            // AC-14 / ADR-0019: no plan-based limit; the organization's plan is not read.
            $locked->status = TripStatus::Published;
            $locked->published_at = now();
            $locked->save();

            $this->audit->record(AuditAction::TripPublished, $locked, ['status' => TripStatus::Draft->value], ['status' => TripStatus::Published->value]);
        });
    }

    /**
     * AC-15. Bookings and their payment pages are untouched.
     */
    public function unpublish(Trip $trip): void
    {
        DB::transaction(function () use ($trip) {
            $locked = Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPublished()) {
                return;
            }

            $locked->status = TripStatus::Draft;
            $locked->save();

            $this->audit->record(AuditAction::TripUnpublished, $locked, ['status' => TripStatus::Published->value], ['status' => TripStatus::Draft->value]);
        });
    }

    /**
     * AC-16. Only trips that never had a booking; departures and photos go with it.
     */
    public function delete(Trip $trip): void
    {
        $paths = DB::transaction(function () use ($trip) {
            $locked = Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();
            $departures = $locked->departures()->lockForUpdate()->get();

            if ($departures->contains(fn (TripDeparture $departure) => $this->bookings->hasAnyBooking($departure))) {
                throw ValidationException::withMessages(['trip' => __('trips.errors.trip_has_bookings')]);
            }

            $departures->each(fn (TripDeparture $departure) => $this->departureAudit->deleted($departure));

            $paths = $locked->photos()->get()->flatMap(fn (TripPhoto $photo) => $photo->allPaths())->all();
            $locked->delete();

            return $paths;
        });

        Storage::disk(config('rakyt.media.public_disk'))->delete($paths);
    }

    public function hasAnyBooking(Trip $trip): bool
    {
        return $trip->departures()->get()->contains(fn (TripDeparture $departure) => $this->bookings->hasAnyBooking($departure));
    }

    /**
     * A published trip keeps its name and base price (they are checklist items).
     *
     * @param  array<string, mixed>  $data
     */
    private function assertPublishedStaysComplete(Trip $trip, array $data): void
    {
        if ($trip->isPublished() && array_key_exists('base_price', $data) && $data['base_price'] === null) {
            throw ValidationException::withMessages(['base_price' => __('trips.errors.published_needs_price')]);
        }
    }

    /**
     * Generated once from the name, unique in the organization, never changed later.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 90, '') ?: 'trip';
        $base = rtrim($base, '-');
        $rule = new UniqueInCurrentOrganization(Trip::class, 'slug');

        for ($n = 1; ; $n++) {
            $candidate = $n === 1 ? $base : "{$base}-{$n}";

            if (! $rule->isTaken($candidate)) {
                return $candidate;
            }
        }
    }
}
