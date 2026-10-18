<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside asTenant(): the organization comes from the current context.
 *
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'bank',
            'provider' => 'bca',
            'provider_name' => null,
            'account_number' => (string) fake()->numerify('##########'),
            'account_holder' => 'Agus Santoso',
            'position' => 0,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
