<?php

namespace App\Http\Requests\Trips;

use App\Trips\DeadlinePreset;
use App\Trips\DepartureStatus;
use App\Trips\TripRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Departure fields (AC-17–AC-24). Shared with F-001 onboarding (4d).
 */
class DepartureRequest extends FormRequest
{
    public const CAPACITY_MAX = 999;

    protected bool $partial = false;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $sometimes = $this->partial ? ['sometimes'] : [];

        return [
            'start_date' => [...$sometimes, 'required', 'date_format:Y-m-d'],
            'capacity' => [...$sometimes, 'required', 'integer', 'between:1,'.self::CAPACITY_MAX],
            'price_override' => [...$sometimes, 'nullable', 'integer', 'between:'.TripRules::PRICE_MIN.','.TripRules::PRICE_MAX],
            'booking_deadline_preset' => [...$sometimes, $this->partial ? 'required' : 'nullable', Rule::enum(DeadlinePreset::class)],
            'booking_deadline_at' => ['nullable', 'string', 'max:20', 'required_if:booking_deadline_preset,custom'],
            'status' => ['sometimes', 'required', Rule::enum(DepartureStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'capacity.between' => __('trips.errors.capacity_range'),
            'capacity.integer' => __('trips.errors.capacity_range'),
            'price_override.between' => __('trips.errors.price_range'),
            'price_override.integer' => __('trips.errors.price_range'),
            'booking_deadline_at.required_if' => __('trips.errors.deadline_custom_required'),
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
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        foreach (['capacity', 'price_override'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }

        return $data;
    }
}
