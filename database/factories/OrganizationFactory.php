<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name' => "Kencana Trip {$n}",
            'slug' => "kencana-{$n}",
            'city' => 'Jepara, Jawa Tengah',
            'whatsapp' => '+6281277881020',
            // Past onboarding by default, so seller pages open normally (F-001 E2/E3).
            'onboarding_skipped_at' => now(),
        ];
    }

    /**
     * Still in the guided first-trip flow: never published, never skipped (F-001 E2).
     */
    public function onboarding(): static
    {
        return $this->state(['onboarding_skipped_at' => null, 'first_published_at' => null]);
    }
}
