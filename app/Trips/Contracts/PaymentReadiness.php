<?php

namespace App\Trips\Contracts;

use App\Models\Organization;

/**
 * Publish checklist item 5 "Instruksi pembayaran" (F-003 AC-9, Q9). F-001 binds
 * the real query once payment methods exist.
 */
interface PaymentReadiness
{
    public function hasActivePaymentMethod(Organization $organization): bool;
}
