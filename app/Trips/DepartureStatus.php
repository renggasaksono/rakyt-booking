<?php

namespace App\Trips;

enum DepartureStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
