<?php

namespace App\Payments;

/**
 * The seller's verdict on a payment_records row (F-006, ADR-0030 §2). Set
 * exactly when verified_at is set; `rejected` is terminal (seats released).
 */
enum PaymentDecision: string
{
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
