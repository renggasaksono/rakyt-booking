<?php

namespace App\Bookings;

/**
 * Set exactly when a booking is `cancelled` (ADR-0026 §1, database CHECK).
 */
enum CancelReason: string
{
    case HoldExpired = 'hold_expired';
    // F-007: the seller cancels.
    case Seller = 'seller';
}
