<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Tenancy\CurrentOrganization;

/**
 * F-001 fixtures. Use with InteractsWithTenants (and BuildsTrips for dates).
 */
trait BuildsOnboarding
{
    /**
     * A seller who signed up but has no organization yet (step 1).
     */
    protected function signedUpUser(array $attributes = []): User
    {
        return User::factory()->unverified()->create(['signup_started_at' => now(), 'terms_accepted_at' => now(), ...$attributes]);
    }

    /**
     * A seller whose organization exists but is still in the guided flow (E2).
     */
    protected function onboardingTenant(array $attributes = []): Tenant
    {
        $organization = Organization::factory()->onboarding()->create($attributes);
        $owner = $this->signedUpUser();

        app(CurrentOrganization::class)->runAs($organization, fn () => OrganizationMember::factory()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $owner->getKey(),
        ]));

        return new Tenant($organization, $owner);
    }

    /**
     * @return array<string, string>
     */
    protected function organizationInput(array $overrides = []): array
    {
        return [
            'name' => 'Kencana Trip',
            'city' => 'Jepara, Jawa Tengah',
            'whatsapp' => '0812 7788 1020',
            'slug' => 'kencana',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tripInput(array $overrides = []): array
    {
        return [
            'type' => 'open_trip',
            'name' => 'Open Trip Karimunjawa 3H2M',
            'duration_label' => 'days',
            'duration_days' => 3,
            'duration_nights' => 2,
            'inclusions' => ['Transportasi', 'Makan'],
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scheduleInput(array $overrides = []): array
    {
        return [
            'base_price' => 1_250_000,
            'start_date' => '2024-10-24',
            'capacity' => 16,
            'booking_deadline_preset' => 'h3',
            'booking_deadline_at' => null,
            ...$overrides,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function bankBlock(array $overrides = []): array
    {
        return [
            'provider' => 'bca',
            'account_number' => '0123 4567 89',
            'account_holder' => 'Agus Santoso',
            ...$overrides,
        ];
    }
}
