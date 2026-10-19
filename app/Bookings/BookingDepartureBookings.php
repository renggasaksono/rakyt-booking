<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\TripDeparture;
use App\Trips\Contracts\DepartureBookings;

/**
 * F-003's view of bookings (F-005 AC-41), replacing the seats_taken-only default.
 */
final class BookingDepartureBookings implements DepartureBookings
{
    /**
     * Cancelled bookings count too (F-003 AC-16, AC-29); taken seats always
     * mean a booking (seat invariant, AC-23).
     */
    public function hasAnyBooking(TripDeparture $departure): bool
    {
        return $departure->seats_taken > 0
            || Booking::query()->where('trip_departure_id', $departure->getKey())->exists();
    }

    /**
     * Bookings in holding statuses (F-003 AC-32's "n pesanan yang ada").
     */
    public function activeBookingCount(TripDeparture $departure): int
    {
        return Booking::query()
            ->where('trip_departure_id', $departure->getKey())
            ->whereIn('status', BookingStatus::holdingValues())
            ->count();
    }
}
