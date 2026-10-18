<?php

namespace App\Payments;

use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Tenancy\CurrentOrganization;
use App\Trips\Contracts\PaymentReadiness;

/**
 * Publish checklist item 5 (F-003 AC-9, Q9): the organization has at least
 * one active payment method. Replaces F-003's NoPaymentMethodsYet.
 */
final class ActivePaymentMethods implements PaymentReadiness
{
    public function __construct(private CurrentOrganization $current) {}

    public function hasActivePaymentMethod(Organization $organization): bool
    {
        return $this->current->runAs(
            $organization,
            fn () => PaymentMethod::query()->where('active', true)->exists(),
        );
    }
}
