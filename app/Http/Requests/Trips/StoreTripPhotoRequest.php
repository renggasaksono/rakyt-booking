<?php

namespace App\Http\Requests\Trips;

use App\Media\UploadRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One photo per request, so each shows its own progress (AC-7).
 */
class StoreTripPhotoRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['photo' => UploadRules::tripPhoto()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.mimetypes' => __('trips.errors.photo_type'),
            'photo.file' => __('trips.errors.photo_type'),
            'photo.uploaded' => __('trips.errors.photo_size'),
            'photo.max' => __('trips.errors.photo_size'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('trips.attributes');
    }
}
