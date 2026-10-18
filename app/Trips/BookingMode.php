<?php

namespace App\Trips;

enum BookingMode: string
{
    case Scheduled = 'scheduled';
    case RequestDate = 'request_date';
}
