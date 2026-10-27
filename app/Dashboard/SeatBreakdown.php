<?php

namespace App\Dashboard;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\TripDeparture;
use Illuminate\Database\Eloquent\Builder;

/**
 * The seat summary of a departure (F-007 AC-12): lunas = Σ paid participants;
 * ditahan = Σ awaiting_payment + needs_review (an expired, unreleased hold
 * still holds its seats); kosong = capacity − seats_taken. One grouped query
 * for any number of departures; lunas + ditahan = seats_taken (seat
 * invariant). Shared by "Hari ini" and the admin seller detail (F-012 AC-4),
 * which pass their own (scoped or admin) booking query.
 */
final class SeatBreakdown
{
    /**
     * @param  Builder<Booking>  $bookings
     * @param  list<int>  $departureIds
     * @return array<int, array{paid: int, held: int}>
     */
    public static function sums(Builder $bookings, array $departureIds): array
    {
        if ($departureIds === []) {
            return [];
        }

        $sums = $bookings
            ->whereIn('trip_departure_id', $departureIds)
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

    /**
     * @param  array<int, array{paid: int, held: int}>  $sums
     * @return array{capacity: int, paid: int, held: int, empty: int}
     */
    public static function seats(TripDeparture $departure, array $sums): array
    {
        return [
            'capacity' => $departure->capacity,
            'paid' => $sums[$departure->getKey()]['paid'] ?? 0,
            'held' => $sums[$departure->getKey()]['held'] ?? 0,
            'empty' => max(0, $departure->capacity - $departure->seats_taken),
        ];
    }
}
