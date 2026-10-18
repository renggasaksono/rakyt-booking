<?php

namespace App\Http\Requests\Trips;

use App\Trips\TripRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Trip fields, normalized then validated with the shared TripRules.
 */
abstract class TripFieldsRequest extends FormRequest
{
    abstract protected function partial(): bool;

    protected function prepareForValidation(): void
    {
        $this->replace([...$this->except(TripRules::FIELDS), ...TripRules::normalize($this->all())]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return TripRules::rules($this->partial());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TripRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('trips.attributes');
    }

    /**
     * Validated trip fields with integers cast.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = array_intersect_key($this->validated(), array_flip(TripRules::FIELDS));

        foreach (['duration_days', 'duration_nights', 'base_price'] as $key) {
            if (isset($fields[$key])) {
                $fields[$key] = (int) $fields[$key];
            }
        }

        return $fields;
    }
}
