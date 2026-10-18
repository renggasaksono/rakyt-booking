<?php

namespace App\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;

/**
 * The live preview card (F-003 AC-13), matching the F-002 trip card.
 */
final class TripPreview
{
    /**
     * @return array{cover_url: ?string, name: string, from_price: ?int, next_departure: ?array{start_date: string, seats_left: int}}
     */
    public static function for(Trip $trip): array
    {
        $today = TripDeparture::today($trip->organization);

        $open = $trip->departures()
            ->where('status', DepartureStatus::Open)
            ->whereDate('start_date', '>=', $today)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $prices = $open->map(fn (TripDeparture $departure) => $departure->price_override ?? $trip->base_price)->filter(fn ($price) => $price !== null);

        $next = $open->first(fn (TripDeparture $departure) => $departure->booking_deadline_at->greaterThan(CarbonImmutable::now()));

        $cover = $trip->photos()->first();

        return [
            'cover_url' => $cover?->url(800),
            'name' => $trip->name,
            // Lowest effective price among open future departures, else the base price.
            'from_price' => $prices->isNotEmpty() ? $prices->min() : $trip->base_price,
            'next_departure' => $next === null ? null : [
                'start_date' => $next->start_date->toDateString(),
                'seats_left' => $next->seatsLeft(),
            ],
        ];
    }
}
