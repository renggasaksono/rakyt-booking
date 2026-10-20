<?php

namespace App\Payments;

enum PaymentMethodType: string
{
    case Bank = 'bank';
    case Ewallet = 'ewallet';
    // F-006, ADR-0030 §4: a QR image instead of an account number.
    case Qris = 'qris';
}
