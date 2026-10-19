<?php

namespace App\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;

/**
 * The live preview card (F-003 AC-13), matching the F-002 trip card: the same
 * "Mulai Rp…" function and the same departures (open for booking, F-002 Q7).
 */
final class TripPreview
{
    /**
     * @return array{cover_url: ?string, name: string, from_price: ?int, next_departure: ?array{start_date: string, seats_left: int}}
     */
    public static function for(Trip $trip): array
    {
        $bookable = $trip->departures()
            ->openForBooking(TripDeparture::today($trip->organization))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $next = $trip->isScheduled() ? $bookable->first() : null;

        $cover = $trip->photos()->first();

        return [
            'cover_url' => $cover?->url(800),
            'name' => $trip->name,
            'from_price' => TripPricing::fromPrice($trip, $bookable),
            'next_departure' => $next === null ? null : [
                'start_date' => $next->start_date->toDateString(),
                'seats_left' => $next->seatsLeft(),
            ],
        ];
    }
}
