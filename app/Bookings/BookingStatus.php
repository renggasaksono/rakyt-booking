<?php

namespace App\Bookings;

/**
 * One booking status column, the glossary set (ADR-0026 §1). F-005 creates
 * `awaiting_payment` and releases expired holds to `cancelled`; F-006 adds
 * `needs_review`, `paid` and `rejected`.
 */
enum BookingStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case NeedsReview = 'needs_review';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /**
     * Statuses whose participants count in trip_departures.seats_taken (ADR-0026 §2).
     *
     * @return list<self>
     */
    public static function holding(): array
    {
        return [self::AwaitingPayment, self::NeedsReview, self::Paid];
    }

    /**
     * @return list<string>
     */
    public static function holdingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::holding());
    }

    public function holdsSeats(): bool
    {
        return in_array($this, self::holding(), true);
    }
}
