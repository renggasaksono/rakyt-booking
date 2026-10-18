<?php

namespace App\Http\Requests\Trips;

/**
 * "Simpan perubahan" (AC-4).
 */
class UpdateTripRequest extends TripFieldsRequest
{
    protected function partial(): bool
    {
        return false;
    }
}
