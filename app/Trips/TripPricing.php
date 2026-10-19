<?php

namespace App\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;

/**
 * "Mulai Rp…" (F-002 AC-15, F-003 AC-13): the lowest effective price among the
 * trip's bookable departures, else the base price. Shared by the public trip
 * card and the editor preview so they always agree (F-002 Q7).
 */
final class TripPricing
{
    /**
     * @param  iterable<TripDeparture>  $bookable  the trip's bookable departures (callers query them)
     */
    public static function fromPrice(Trip $trip, iterable $bookable): ?int
    {
        if (! $trip->isScheduled()) {
            return $trip->base_price;
        }

        $lowest = null;

        foreach ($bookable as $departure) {
            $price = $departure->price_override ?? $trip->base_price;

            if ($price !== null && ($lowest === null || $price < $lowest)) {
                $lowest = $price;
            }
        }

        return $lowest ?? $trip->base_price;
    }
}
