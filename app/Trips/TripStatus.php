<?php

namespace App\Trips;

enum TripStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
