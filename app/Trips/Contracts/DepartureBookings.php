<?php

namespace App\Trips\Contracts;

use App\Models\TripDeparture;

/**
 * What F-003 needs to know about bookings, which F-005 owns. F-005 binds the
 * real implementation over its bookings table.
 */
interface DepartureBookings
{
    /**
     * Whether the departure has ever had a booking, cancelled ones included (AC-16, AC-29).
     */
    public function hasAnyBooking(TripDeparture $departure): bool;

    /**
     * Bookings that still hold seats or are paid (AC-32's "n pesanan yang ada").
     */
    public function activeBookingCount(TripDeparture $departure): int;
}
