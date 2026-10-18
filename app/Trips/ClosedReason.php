<?php

namespace App\Trips;

enum ClosedReason: string
{
    case Manual = 'manual';
    case Deadline = 'deadline';
}
