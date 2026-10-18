<?php

namespace App\Http\Requests\Trips;

use App\Trips\DeadlinePreset;
use App\Trips\DepartureStatus;
use App\Trips\TripRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Quick edit sheet (1g, AC-31–AC-34): price, total seats, open/closed, and a
 * new deadline only when reopening after the old one passed.
 */
class QuickEditDepartureRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'between:'.TripRules::PRICE_MIN.','.TripRules::PRICE_MAX],
            'capacity' => ['required', 'integer', 'between:1,'.DepartureRequest::CAPACITY_MAX],
            'status' => ['required', Rule::enum(DepartureStatus::class)],
            'booking_deadline_preset' => ['nullable', Rule::enum(DeadlinePreset::class)],
            'booking_deadline_at' => ['nullable', 'string', 'max:20', 'required_if:booking_deadline_preset,custom'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.between' => __('trips.errors.price_range'),
            'price.integer' => __('trips.errors.price_range'),
            'capacity.between' => __('trips.errors.capacity_range'),
            'capacity.integer' => __('trips.errors.capacity_range'),
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
     * @return array{price: int, capacity: int, status: string, booking_deadline_preset: ?string, booking_deadline_at: ?string}
     */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'price' => (int) $data['price'],
            'capacity' => (int) $data['capacity'],
            'status' => $data['status'],
            'booking_deadline_preset' => $data['booking_deadline_preset'] ?? null,
            'booking_deadline_at' => $data['booking_deadline_at'] ?? null,
        ];
    }
}
