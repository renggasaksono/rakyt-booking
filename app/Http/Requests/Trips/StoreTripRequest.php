<?php

namespace App\Http\Requests\Trips;

class StoreTripRequest extends TripFieldsRequest
{
    protected function partial(): bool
    {
        return false;
    }
}
