<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\OrganizationOwnedPolicy;
use Illuminate\Auth\Access\Response;

/**
 * Sellers see only their own bookings (F-005 AC-38, F-007 AC-34). Customers
 * reach a booking through its access token, never through this policy (ADR-0027).
 */
class BookingPolicy extends OrganizationOwnedPolicy
{
    /**
     * The list and Hari ini are scoped to the current organization; without one there is nothing to list.
     */
    public function viewAny(User $user): Response
    {
        return app(CurrentOrganization::class)->check() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * F-007 AC-27, AC-34: the seller cancels their own booking. Whether it still
     * holds seats is decided under the locks (BookingService::cancelBySeller).
     */
    public function cancel(User $user, Booking $booking): Response
    {
        return $this->owns($user, $booking);
    }

    /**
     * F-006 AC-16–AC-18: the seller confirms or rejects their own booking's
     * payment. Whether it is still needs_review is decided under the locks
     * (BookingService::confirmPayment / rejectPayment).
     */
    public function verify(User $user, Booking $booking): Response
    {
        return $this->owns($user, $booking);
    }
}
