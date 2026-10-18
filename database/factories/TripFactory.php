<?php

namespace Database\Factories;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Create inside asTenant(): the organization comes from the current context.
 *
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Karimunjawa '.fake()->unique()->numberBetween(1, 99999).' Open Trip';

        return [
            'type' => 'open_trip',
            'booking_mode' => 'scheduled',
            'name' => $name,
            'duration_label' => 'days',
            'duration_days' => 3,
            'duration_nights' => 2,
            'meeting_point' => 'Pelabuhan Jepara',
            'meeting_time' => '06:00',
            'base_price' => 1_250_000,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Trip $trip) {
            $trip->slug ??= Str::slug($trip->name);
        });
    }

    public function published(): static
    {
        return $this->afterMaking(function (Trip $trip) {
            $trip->status = 'published';
            $trip->published_at = now();
        });
    }

    public function requestDate(): static
    {
        return $this->state(['type' => 'private', 'booking_mode' => 'request_date']);
    }
}
