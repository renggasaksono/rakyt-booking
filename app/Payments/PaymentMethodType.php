<?php

namespace App\Payments;

enum PaymentMethodType: string
{
    case Bank = 'bank';
    case Ewallet = 'ewallet';
}
