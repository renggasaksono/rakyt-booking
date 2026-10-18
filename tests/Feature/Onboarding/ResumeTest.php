<?php

namespace Tests\Feature\Onboarding;

use App\Models\PaymentMethod;
use App\Models\Trip;
use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOnboarding;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-001 E1–E3: where a seller lands, derived from data only.
 */
class ResumeTest extends TestCase
{
    use BuildsOnboarding, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    public function test_ac_e1_a_seller_without_an_organization_goes_to_step_1_from_any_seller_page(): void
    {
        $user = $this->signedUpUser();

        foreach (['trips.index', 'dashboard', 'settings.index', 'settings.profile', 'settings.booking.edit', 'departures.upcoming', 'onboarding'] as $route) {
            $this->actingAs($user)->get(route($route))->assertRedirect(route('onboarding.organization'));
        }
    }

    public function test_ac_e2_the_resume_step_follows_the_data(): void
    {
        $this->freezeClock();
        $tenant = $this->onboardingTenant();
        $resume = fn () => $this->actingAsTenant($tenant)->get(route('trips.index'));

        // Organization, no trip → 2.
        $resume()->assertRedirect(route('onboarding.trip'));

        // Trip without a price → 3.
        $trip = $this->asTenant($tenant, fn () => Trip::factory()->create(['base_price' => null]));
        $resume()->assertRedirect(route('onboarding.schedule'));

        // Scheduled trip with a price but no departure → 3.
        $trip->update(['base_price' => 1_000_000]);
        $resume()->assertRedirect(route('onboarding.schedule'));

        // Departure, no payment method → 4.
        $this->departureFor($tenant, $trip);
        $resume()->assertRedirect(route('onboarding.payment'));

        // Everything saved but not published → still 4.
        $this->asTenant($tenant, fn () => PaymentMethod::factory()->create());
        $resume()->assertRedirect(route('onboarding.payment'));
    }

    public function test_ac_e2_request_date_trips_need_no_departure(): void
    {
        $tenant = $this->onboardingTenant();
        $this->asTenant($tenant, fn () => Trip::factory()->create(['type' => 'private', 'booking_mode' => 'request_date']));

        $this->actingAsTenant($tenant)->get(route('dashboard'))->assertRedirect(route('onboarding.payment'));
    }

    public function test_ac_e2_sign_in_resumes_with_the_saved_values(): void
    {
        $this->freezeClock();
        $tenant = $this->onboardingTenant(['name' => 'Kencana']);
        $trip = $this->asTenant($tenant, fn () => Trip::factory()->create(['name' => 'Karimunjawa 3H2M']));

        $this->post(route('login'), ['email' => $tenant->owner->email, 'password' => 'password'])
            ->assertRedirect(route('onboarding.schedule'));

        $this->get(route('onboarding.trip'))->assertInertia(fn ($page) => $page->where('trip.name', 'Karimunjawa 3H2M'));
        $this->get(route('onboarding.organization'))->assertInertia(fn ($page) => $page->where('organization.name', 'Kencana'));
        $this->assertNotNull($trip);
    }

    public function test_ac_e2_writes_and_json_still_reach_the_trip_routes_during_onboarding(): void
    {
        $tenant = $this->onboardingTenant();
        $trip = $this->asTenant($tenant, fn () => Trip::factory()->create());

        $this->actingAsTenant($tenant)->getJson(route('trips.edit', $trip))->assertOk();
        $this->actingAsTenant($tenant)->patchJson(route('trips.autosave', $trip), ['name' => 'Nama baru'])->assertOk();
    }

    public function test_ac_e3_after_a_draft_or_a_publish_the_seller_lands_on_the_dashboard(): void
    {
        $skipped = $this->onboardingTenant(['onboarding_skipped_at' => now()]);
        $published = $this->onboardingTenant(['first_published_at' => now()]);

        foreach ([$skipped, $published] as $tenant) {
            $this->post(route('login'), ['email' => $tenant->owner->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
            $this->get(route('dashboard'))->assertRedirect(route('trips.index'));
            $this->get(route('trips.index'))->assertOk();
            $this->get(route('onboarding.trip'))->assertRedirect(route('dashboard'));
            $this->post(route('logout'));
        }
    }

    public function test_ac_e3_the_draft_trip_is_in_trip_dan_keberangkatan(): void
    {
        $tenant = $this->onboardingTenant(['onboarding_skipped_at' => now()]);
        $trip = $this->asTenant($tenant, fn () => Trip::factory()->create(['name' => 'Draf Karimunjawa']));

        $this->actingAsTenant($tenant)->get(route('trips.index'))
            ->assertInertia(fn ($page) => $page->where('trips.0.name', 'Draf Karimunjawa')->where('trips.0.status', 'draft'));
        $this->actingAsTenant($tenant)->get(route('trips.edit', $trip))->assertOk();
        $this->assertSame(0, TripDeparture::acrossOrganizations()->count());
    }
}
