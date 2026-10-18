<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside asTenant(). Defaults: two weeks ahead, 16 seats, H-3 deadline (WIB).
 *
 * @extends Factory<TripDeparture>
 */
class TripDepartureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(14)->startOfDay();

        return [
            'trip_id' => Trip::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays(2)->toDateString(),
            'capacity' => 16,
            'price_override' => null,
            'booking_deadline_preset' => 'h3',
            'booking_deadline_at' => $start->subDays(3)->setTime(23, 59, 59)->utc(),
            'status' => 'open',
        ];
    }

    /**
     * Starting on the given local date with an H-3 deadline (WIB).
     */
    public function startingOn(CarbonImmutable|string $date, string $timezone = 'Asia/Jakarta'): static
    {
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay();

        return $this->state([
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays(2)->toDateString(),
            'booking_deadline_at' => $start->subDays(3)->setTime(23, 59, 59)->utc(),
        ]);
    }

    /**
     * Seats already taken (F-005 maintains this in production).
     */
    public function withSeatsTaken(int $seats): static
    {
        return $this->afterMaking(fn (TripDeparture $departure) => $departure->seats_taken = $seats);
    }
}
