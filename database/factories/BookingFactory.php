<?php

namespace Database\Factories;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Rows only: seats are not taken. Tests that need seats use
 * Tests\Support\BuildsBookings, which keeps the seat invariant (F-005 AC-23).
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(32);
        $n = fake()->unique()->numberBetween(1, 999_999);

        return [
            'reference' => sprintf('RKB-TST-1024-%04d', $n),
            'access_token' => $token,
            'access_token_hash' => Booking::hashToken($token),
            'submission_key' => (string) Str::uuid(),
            'customer_name' => 'Rina Wulandari',
            'customer_phone' => '+6281234567890',
            'participants' => 2,
            'notes' => null,
            'unit_price' => 1_250_000,
            'total_amount' => 2_500_000,
            'status' => BookingStatus::AwaitingPayment,
            'cancel_reason' => null,
            'hold_expires_at' => CarbonImmutable::now()->addMinutes((int) config('rakyt.booking.hold_minutes')),
        ];
    }
}
