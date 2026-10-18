<?php

namespace App\Trips;

use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripPhoto;
use App\Trips\Contracts\DepartureBookings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Props for the seller's trip pages. Values are ISO in code; the Vue pages
 * format them for display (Indonesian locale).
 */
final class TripPresenter
{
    public function __construct(
        private PublishChecklist $checklist,
        private DepartureBookings $bookings,
        private DepartureService $departures,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function editor(?Trip $trip, Organization $organization): array
    {
        $trip?->setRelation('organization', $organization);

        return [
            'trip' => $trip ? $this->trip($trip) : null,
            'departures' => $trip ? $this->departureGroups($trip) : ['upcoming' => [], 'past' => []],
            'copyTemplate' => $trip ? $this->departures->copyTemplate($trip) : null,
            'checklist' => $this->checklist($trip, $organization),
            'preview' => $trip ? TripPreview::for($trip) : null,
            'context' => $this->context($organization),
            'options' => $this->options(),
            'urls' => $this->urls($trip),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trip(Trip $trip): array
    {
        return [
            'id' => $trip->public_id,
            'slug' => $trip->slug,
            'status' => $trip->status->value,
            'type' => $trip->type->value,
            'booking_mode' => $trip->booking_mode->value,
            'name' => $trip->name,
            'short_description' => $trip->short_description,
            'duration_label' => $trip->duration_label,
            'duration_days' => $trip->duration_days,
            'duration_nights' => $trip->duration_nights,
            'duration_display' => $trip->duration()?->display(),
            'inclusions' => $trip->inclusions ?? [],
            'exclusions' => $trip->exclusions ?? [],
            'meeting_point' => $trip->meeting_point,
            'meeting_time' => $trip->meeting_time,
            'map_url' => $trip->map_url,
            'itinerary' => $trip->itinerary ?? [],
            'terms' => $trip->terms,
            'base_price' => $trip->base_price,
            'photos' => $this->photos($trip),
            'can_delete' => ! $trip->departures()->get()->contains(fn (TripDeparture $departure) => $this->bookings->hasAnyBooking($departure)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function photos(Trip $trip): array
    {
        return $trip->photos()->get()->map(fn (TripPhoto $photo) => [
            'id' => $photo->public_id,
            'url' => $photo->url(400),
            'position' => $photo->position,
            'destroy_url' => route('trips.photos.destroy', [$trip, $photo]),
        ])->all();
    }

    /**
     * AC-26: upcoming ascending; past ones grouped, read-only.
     *
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public function departureGroups(Trip $trip): array
    {
        $today = TripDeparture::today($trip->organization);
        $all = $trip->departures()->orderBy('start_date')->orderBy('id')->get()
            ->each(fn (TripDeparture $departure) => $departure->setRelation('trip', $trip));

        [$upcoming, $past] = $all->partition(fn (TripDeparture $departure) => $departure->start_date->toDateString() >= $today);

        return [
            'upcoming' => $upcoming->map(fn (TripDeparture $departure) => $this->departure($departure))->values()->all(),
            'past' => $past->sortByDesc('start_date')->map(fn (TripDeparture $departure) => $this->departure($departure))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function departure(TripDeparture $departure): array
    {
        $trip = $departure->trip;
        $timezone = $trip->organization->timezone;
        $isPast = $departure->hasStarted();
        $deadlinePassed = $departure->deadlinePassed();
        $hasBookings = $this->bookings->hasAnyBooking($departure);

        return [
            'id' => $departure->public_id,
            'trip_id' => $trip->public_id,
            'trip_name' => $trip->name,
            'trip_meeting_time' => $trip->meeting_time,
            'trip_duration_days' => $trip->duration_days,
            'start_date' => $departure->start_date->toDateString(),
            'end_date' => $departure->end_date->toDateString(),
            'capacity' => $departure->capacity,
            'seats_taken' => $departure->seats_taken,
            'price_override' => $departure->price_override,
            'effective_price' => $departure->effectivePrice(),
            'booking_deadline_preset' => $departure->booking_deadline_preset->value,
            'booking_deadline_local' => LocalTime::input($departure->booking_deadline_at, $timezone),
            'deadline_short' => LocalTime::short($departure->booking_deadline_at, $timezone),
            'deadline_long' => LocalTime::long($departure->booking_deadline_at, $timezone),
            'deadline_passed' => $deadlinePassed,
            'status' => $departure->status->value,
            'closed_reason' => $departure->closed_reason?->value,
            'display_status' => self::displayStatus($departure, $isPast, $deadlinePassed),
            'is_past' => $isPast,
            'can_change_date' => $departure->seats_taken === 0,
            'can_delete' => ! $isPast && ! $hasBookings,
            'active_bookings' => $this->bookings->activeBookingCount($departure),
            'urls' => [
                'update' => route('trips.departures.update', [$trip, $departure]),
                'quick' => route('trips.departures.quick', [$trip, $departure]),
                'destroy' => route('trips.departures.destroy', [$trip, $departure]),
            ],
        ];
    }

    /**
     * AC-27: Sudah lewat > Tutup (manual or deadline) > Penuh > Buka.
     */
    public static function displayStatus(TripDeparture $departure, bool $isPast, bool $deadlinePassed): string
    {
        return match (true) {
            $isPast => 'past',
            ! $departure->isOpen() || $deadlinePassed => 'closed',
            $departure->isFull() => 'full',
            default => 'open',
        };
    }

    /**
     * @return array{items: list<array{key: string, done: bool}>, done: int, total: int}
     */
    public function checklist(?Trip $trip, Organization $organization): array
    {
        $items = $trip !== null
            ? $this->checklist->items($trip)
            : [
                PublishChecklist::COVER_PHOTO => false,
                PublishChecklist::NAME => false,
                PublishChecklist::BASE_PRICE => false,
                PublishChecklist::DEPARTURE => false,
                PublishChecklist::PAYMENT_INSTRUCTIONS => app(Contracts\PaymentReadiness::class)->hasActivePaymentMethod($organization),
            ];

        return [
            'items' => array_map(fn (string $key, bool $done) => ['key' => $key, 'done' => $done], array_keys($items), $items),
            'done' => count(array_filter($items)),
            'total' => count($items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function context(Organization $organization): array
    {
        $now = CarbonImmutable::now($organization->timezone);

        return [
            'timezone' => $organization->timezone,
            'today' => $now->toDateString(),
            'now_local' => $now->format('Y-m-d H:i'),
            'default_deadline_preset' => $organization->default_booking_deadline_preset,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $types = [];

        foreach (TripType::cases() as $type) {
            $preset = $type->preset();
            $types[] = [
                'value' => $type->value,
                'booking_mode' => $preset['booking_mode']->value,
                'duration' => $preset['duration']?->toColumns(),
            ];
        }

        $durations = [];

        foreach (Duration::presets() as $key => $duration) {
            $durations[] = ['key' => $key, ...$duration->toColumns(), 'display' => $duration->display()];
        }

        return [
            'types' => $types,
            'durations' => $durations,
            'deadline_presets' => array_map(fn (DeadlinePreset $preset) => $preset->value, DeadlinePreset::cases()),
            'inclusion_suggestions' => __('trips.inclusion_suggestions'),
            'limits' => [
                'name' => TripRules::NAME_MAX,
                'short_description' => TripRules::SHORT_DESCRIPTION_MAX,
                'list_items' => TripRules::LIST_MAX_ITEMS,
                'list_item' => TripRules::LIST_ITEM_MAX,
                'meeting_point' => TripRules::MEETING_POINT_MAX,
                'itinerary_line' => TripRules::ITINERARY_LINE_MAX,
                'terms' => TripRules::TERMS_MAX,
                'price_min' => TripRules::PRICE_MIN,
                'price_max' => TripRules::PRICE_MAX,
                'capacity_max' => 999,
                'max_days' => Duration::MAX_DAYS,
                'photos' => (int) config('rakyt.media.trip_photo.max_per_trip'),
                'photo_max_kb' => (int) config('rakyt.media.trip_photo.max_kb'),
                'photo_types' => config('rakyt.media.trip_photo.mimetypes'),
            ],
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function urls(?Trip $trip): array
    {
        return [
            'index' => route('trips.index'),
            'store' => route('trips.store'),
            'settings' => route('settings.booking.edit'),
            // Hidden until F-001 adds the payment-method screen (Q9).
            'payment_setup' => Route::has('payment-methods.index') ? route('payment-methods.index') : null,
            'edit' => $trip ? route('trips.edit', $trip) : null,
            'autosave' => $trip ? route('trips.autosave', $trip) : null,
            'update' => $trip ? route('trips.update', $trip) : null,
            'publish' => $trip ? route('trips.publish', $trip) : null,
            'unpublish' => $trip ? route('trips.unpublish', $trip) : null,
            'destroy' => $trip ? route('trips.destroy', $trip) : null,
            'photos' => $trip ? route('trips.photos.store', $trip) : null,
            'photos_reorder' => $trip ? route('trips.photos.reorder', $trip) : null,
            'departures' => $trip ? route('trips.departures.store', $trip) : null,
        ];
    }

    /**
     * Trips list (index).
     *
     * @param  Collection<int, Trip>  $trips
     * @return list<array<string, mixed>>
     */
    public function list(Collection $trips, Organization $organization): array
    {
        return $trips->map(function (Trip $trip) use ($organization) {
            $trip->setRelation('organization', $organization);
            $preview = TripPreview::for($trip);

            return [
                'id' => $trip->public_id,
                'name' => $trip->name,
                'status' => $trip->status->value,
                'booking_mode' => $trip->booking_mode->value,
                'cover_url' => $preview['cover_url'],
                'from_price' => $preview['from_price'],
                'next_departure' => $preview['next_departure'],
                'edit_url' => route('trips.edit', $trip),
            ];
        })->all();
    }
}
