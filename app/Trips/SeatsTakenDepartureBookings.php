<?php

namespace App\Trips;

use App\Models\TripDeparture;
use App\Trips\Contracts\DepartureBookings;

/**
 * Default until F-005 adds bookings: the only signal is seats_taken. A departure
 * with taken seats counts as booked; the number of bookings is unknown (0).
 */
final class SeatsTakenDepartureBookings implements DepartureBookings
{
    public function hasAnyBooking(TripDeparture $departure): bool
    {
        return $departure->seats_taken > 0;
    }

    public function activeBookingCount(TripDeparture $departure): int
    {
        return 0;
    }
}
