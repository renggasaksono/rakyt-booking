<?php

namespace App\Dashboard;

use App\Bookings\BookingFilters;
use App\Bookings\BookingStatus;
use App\Bookings\SellerBookingPresenter;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Organizations\BookingSlug;
use App\Trips\TripPresenter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "Hari ini" (F-007 section B, mockups 1e/2f). Every read goes through the
 * tenant-scoped Booking / TripDeparture queries, and the page runs a fixed
 * number of queries whatever the number of bookings (AC-15): Perlu tindakan
 * (rows + count), Pesanan terbaru, the next 3 departures with their trips,
 * one grouped seat query, and F-003's per-departure presenter for at most 3.
 */
final class DashboardPresenter
{
    public const ACTION_NEEDED_LIMIT = 5;

    public const RECENT_LIMIT = 5;

    public const DEPARTURES_LIMIT = 3;

    public function __construct(
        private SellerBookingPresenter $bookings,
        private TripPresenter $trips,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(Organization $organization, User $user): array
    {
        $now = CarbonImmutable::now();
        $local = $now->setTimezone($organization->timezone)->locale('id');

        return [
            'greeting' => [
                'text' => __('dashboard.greeting.'.self::period((int) $local->format('G')), ['name' => $user->name]),
                // AC-5: "Jumat, 16 Oktober" — today is always the current year.
                'date' => $local->translatedFormat('l, j F'),
            ],
            'public_url' => BookingSlug::url($organization->slug),
            'action_needed' => [
                'count' => self::actionNeededCount($now),
                'items' => $this->actionNeeded($now),
            ],
            'recent' => $this->recent($now),
            'departures' => $this->departures($organization, $now),
            'context' => [
                ...$this->trips->context($organization),
                'now' => $now->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
            'copy' => [
                ...__('dashboard'),
                'bookings' => __('bookings'),
                'status_words' => __('booking.status'),
                'trips' => __('trips'),
            ],
            'urls' => [
                'new_trip' => route('trips.create'),
                'bookings' => route('bookings.index'),
                'action_needed' => route('bookings.index', ['status' => BookingFilters::ACTION_NEEDED]),
                'departures' => route('departures.upcoming'),
                'trips' => route('trips.index'),
                'public_page' => route('public.seller', ['organization' => $organization->slug]),
            ],
        ];
    }

    /**
     * AC-5: pagi 04.00–10.59, siang 11.00–14.59, sore 15.00–17.59, malam 18.00–03.59.
     */
    public static function period(int $hour): string
    {
        return match (true) {
            $hour >= 4 && $hour < 11 => 'pagi',
            $hour >= 11 && $hour < 15 => 'siang',
            $hour >= 15 && $hour < 18 => 'sore',
            default => 'malam',
        };
    }

    /**
     * AC-4, AC-8: the Perlu tindakan count of the current organization, one query.
     */
    public static function actionNeededCount(?CarbonInterface $now = null): int
    {
        return Booking::query()->actionNeeded($now ?? CarbonImmutable::now())->count();
    }

    /**
     * AC-8: needs_review oldest status change first (updated_at until F-006),
     * then active holds soonest release first; at most 5.
     *
     * @return list<array<string, mixed>>
     */
    private function actionNeeded(CarbonImmutable $now): array
    {
        $rows = Booking::query()
            ->with('departure.trip')
            ->actionNeeded($now)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [BookingStatus::NeedsReview->value])
            ->orderByRaw('CASE WHEN status = ? THEN updated_at ELSE hold_expires_at END', [BookingStatus::NeedsReview->value])
            ->orderBy('id')
            ->limit(self::ACTION_NEEDED_LIMIT)
            ->get();

        return $rows->map(fn (Booking $booking) => $this->bookings->item($booking, $now, withReminder: true))->values()->all();
    }

    /**
     * AC-10: the 5 most recently created bookings, any status.
     *
     * @return list<array<string, mixed>>
     */
    private function recent(CarbonImmutable $now): array
    {
        return Booking::query()
            ->with('departure.trip')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Booking $booking) => $this->bookings->item($booking, $now, withReminder: true))
            ->values()
            ->all();
    }

    /**
     * AC-11, AC-12: the next 3 departures (today or later, any state), F-003's
     * quick-edit shape plus the seat breakdown.
     *
     * @return list<array<string, mixed>>
     */
    private function departures(Organization $organization, CarbonImmutable $now): array
    {
        $departures = TripDeparture::query()
            ->with('trip')
            ->whereDate('start_date', '>=', TripDeparture::today($organization, $now))
            ->orderBy('start_date')
            ->orderBy(Trip::query()->select('name')->whereColumn('trips.id', 'trip_departures.trip_id'))
            ->orderBy('id')
            ->limit(self::DEPARTURES_LIMIT)
            ->get()
            ->each(fn (TripDeparture $departure) => $departure->trip->setRelation('organization', $organization));

        $seats = $this->seatBreakdown($departures);

        return $departures->map(function (TripDeparture $departure) use ($seats, $now, $organization) {
            $paid = $seats[$departure->getKey()]['paid'] ?? 0;
            $held = $seats[$departure->getKey()]['held'] ?? 0;
            $open = $departure->isOpen() && ! $departure->deadlinePassed($now);

            return [
                ...$this->trips->departure($departure),
                'trip' => ['name' => $departure->trip->name],
                'seats' => [
                    'capacity' => $departure->capacity,
                    'paid' => $paid,
                    'held' => $held,
                    'empty' => max(0, $departure->capacity - $departure->seats_taken),
                ],
                'closes_on' => $open
                    ? CarbonImmutable::instance($departure->booking_deadline_at)->setTimezone($organization->timezone)->toDateString()
                    : null,
            ];
        })->values()->all();
    }

    /**
     * AC-12: lunas = Σ paid participants; ditahan = Σ awaiting_payment +
     * needs_review (an expired, unreleased hold still holds its seats). One
     * grouped query; lunas + ditahan = seats_taken (seat invariant).
     *
     * @param  Collection<int, TripDeparture>  $departures
     * @return array<int, array{paid: int, held: int}>
     */
    private function seatBreakdown(Collection $departures): array
    {
        if ($departures->isEmpty()) {
            return [];
        }

        $sums = Booking::query()
            ->whereIn('trip_departure_id', $departures->modelKeys())
            ->whereIn('status', BookingStatus::holdingValues())
            ->groupBy('trip_departure_id', 'status')
            ->selectRaw('trip_departure_id, status, SUM(participants) AS seats')
            ->get();

        $out = [];

        foreach ($sums as $row) {
            $id = (int) $row->getAttribute('trip_departure_id');
            $key = $row->getAttribute('status') === BookingStatus::Paid ? 'paid' : 'held';
            $out[$id] ??= ['paid' => 0, 'held' => 0];
            $out[$id][$key] += (int) $row->getAttribute('seats');
        }

        return $out;
    }
}
