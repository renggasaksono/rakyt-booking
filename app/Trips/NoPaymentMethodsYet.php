<?php

namespace App\Trips;

use App\Models\Organization;
use App\Trips\Contracts\PaymentReadiness;

/**
 * Default until F-001 adds payment methods: nobody has one, so nobody can
 * publish yet (F-003 Q9).
 */
final class NoPaymentMethodsYet implements PaymentReadiness
{
    public function hasActivePaymentMethod(Organization $organization): bool
    {
        return false;
    }
}
