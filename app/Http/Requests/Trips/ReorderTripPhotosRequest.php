<?php

namespace App\Http\Requests\Trips;

use App\Tenancy\Rules\ExistsInCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The trip's photo ids in their new order; the first becomes the cover (AC-7).
 */
class ReorderTripPhotosRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'max:'.config('rakyt.media.trip_photo.max_per_trip')],
            'photos.*' => ['bail', 'required', 'string', 'size:26', 'alpha_num:ascii', 'distinct', new ExistsInCurrentOrganization('trip_photos', 'public_id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('trips.attributes');
    }

    /**
     * @return list<string>
     */
    public function photoIds(): array
    {
        return array_values($this->validated('photos'));
    }
}
