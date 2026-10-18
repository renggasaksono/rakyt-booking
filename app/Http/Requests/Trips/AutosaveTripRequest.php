<?php

namespace App\Http\Requests\Trips;

/**
 * Draft autosave (AC-3): only the fields sent are validated and saved.
 */
class AutosaveTripRequest extends TripFieldsRequest
{
    protected function partial(): bool
    {
        return true;
    }
}
