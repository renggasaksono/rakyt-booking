<?php

namespace Tests\Feature\Onboarding;

use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Trip;
use App\Models\TripPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-001 T1–T5 (ADR-0004, F-009): onboarding and profile never cross sellers.
 */
class OnboardingTenantIsolationTest extends TestCase
{
    use BuildsOnboarding, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    private Trip $trip;

    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.public_disk'));
        $this->freezeClock();
        $this->a = $this->onboardingTenant(['slug' => 'seller-a', 'name' => 'Seller A']);
        $this->b = $this->onboardingTenant(['slug' => 'seller-b', 'name' => 'Seller B']);
        $this->trip = $this->tripFor($this->a, ['name' => 'Trip Rahasia A']);
        $this->departureFor($this->a, $this->trip);
        $this->method = $this->asTenant($this->a, fn () => PaymentMethod::factory()->create(['account_number' => '99887766']));
    }

    public function test_ac_t1_onboarding_ignores_an_organization_id_in_the_request(): void
    {
        $this->actingAsTenant($this->b)->post(route('onboarding.organization'), $this->organizationInput(['slug' => 'seller-b', 'organization_id' => $this->a->id()]))
            ->assertRedirect(route('onboarding.trip'));
        $this->actingAsTenant($this->b)->postJson(route('onboarding.trip'), [...$this->tripInput(), 'organization_id' => $this->a->id()])->assertOk();
        $this->actingAsTenant($this->b)->put(route('settings.profile'), ['organization_id' => $this->a->id(), 'name' => 'Diganti']);

        $this->assertDatabaseHas('organizations', ['id' => $this->a->id(), 'name' => 'Seller A']);
        $this->assertSame(1, Trip::acrossOrganizations()->where('organization_id', $this->a->id())->count());
        $this->assertSame(1, Trip::acrossOrganizations()->where('organization_id', $this->b->id())->count());
    }

    public function test_ac_t2_b_cannot_reach_a_s_draft_trip_photos_or_departure(): void
    {
        $photo = $this->asTenant($this->a, fn () => TripPhoto::create([
            'trip_id' => $this->trip->id,
            'path' => "org/{$this->a->organization->public_id}/trips/{$this->trip->public_id}/0.jpg",
            'position' => 0,
        ]));

        $this->assertCrossTenantNotFound($this->b, [
            ['POST', route('trips.photos.store', $this->trip), ['photo' => UploadedFile::fake()->image('x.jpg', 400, 300)]],
            ['PUT', route('trips.photos.reorder', $this->trip), ['photos' => [$photo->public_id]]],
            ['DELETE', route('trips.photos.destroy', [$this->trip, $photo])],
            ['POST', route('trips.publish', $this->trip)],
            ['PATCH', route('trips.autosave', $this->trip), ['name' => 'Diganti']],
        ], function () use ($photo) {
            $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'name' => 'Trip Rahasia A', 'status' => 'draft']);
            $this->assertDatabaseHas('trip_photos', ['id' => $photo->id]);
        });
    }

    public function test_ac_t2_b_cannot_update_or_remove_a_s_payment_method_by_id(): void
    {
        $this->asTenant($this->b, fn () => Trip::factory()->create(['type' => 'private', 'booking_mode' => 'request_date']));

        $this->assertCrossTenantPayloadRejected($this->b, 'POST', route('onboarding.payment'), [
            'intent' => 'publish',
            'methods' => [['id' => $this->method->public_id, ...$this->bankBlock(['account_number' => '1111111'])]],
        ], 'methods.0.id', fn () => $this->assertDatabaseHas('payment_methods', ['id' => $this->method->id, 'account_number' => '99887766']));

        // B's draft save never deletes A's account either.
        $this->actingAsTenant($this->b)->post(route('onboarding.payment'), ['intent' => 'draft', 'methods' => []]);
        $this->assertDatabaseHas('payment_methods', ['id' => $this->method->id]);
    }

    public function test_ac_t3_a_s_draft_and_payment_details_never_reach_b(): void
    {
        $this->actingAsTenant($this->b)->get(route('onboarding.trip'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('trip', null));

        $this->asTenant($this->b, fn () => Trip::factory()->create(['type' => 'private', 'booking_mode' => 'request_date']));
        $response = $this->actingAsTenant($this->b)->get(route('onboarding.payment'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('methods', []));

        $this->assertStringNotContainsString('99887766', $response->getContent());
        $this->assertStringNotContainsString('Trip Rahasia A', $response->getContent());
    }

    public function test_ac_t4_photos_and_logos_live_under_the_organization_prefix_and_b_cannot_touch_a_s(): void
    {
        $this->a->organization->update(['first_published_at' => now()]);
        $this->b->organization->update(['first_published_at' => now()]);

        $this->actingAsTenant($this->a)->post(route('settings.profile.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertSessionHasNoErrors();
        $aLogo = Organization::query()->findOrFail($this->a->id())->logo_path;
        $this->assertStringStartsWith('org/'.$this->a->organization->public_id.'/logo/', $aLogo);

        // B replacing and removing their logo leaves A's file alone.
        $this->actingAsTenant($this->b)->post(route('settings.profile.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)]);
        $this->actingAsTenant($this->b)->delete(route('settings.profile.logo'));

        Storage::disk(config('rakyt.media.public_disk'))->assertExists($aLogo);
        $this->assertSame($aLogo, Organization::query()->findOrFail($this->a->id())->logo_path);
        $this->assertNull(Organization::query()->findOrFail($this->b->id())->logo_path);
    }

    public function test_ac_t5_a_user_without_a_membership_is_denied_every_seller_action(): void
    {
        $stranger = $this->signedUpUser();

        $this->actingAs($stranger)->postJson(route('trips.photos.store', $this->trip), ['photo' => UploadedFile::fake()->image('x.jpg', 400, 300)])->assertNotFound();
        $this->actingAs($stranger)->postJson(route('trips.publish', $this->trip))->assertNotFound();
        $this->actingAs($stranger)->putJson(route('settings.profile'), ['name' => 'X'])->assertNotFound();
        $this->actingAs($stranger)->postJson(route('settings.profile.logo'))->assertNotFound();
        $this->actingAs($stranger)->post(route('onboarding.schedule'), $this->scheduleInput())->assertRedirect(route('onboarding.organization'));
        $this->actingAs($stranger)->post(route('onboarding.payment'), ['intent' => 'publish', 'methods' => [$this->bankBlock()]])->assertRedirect(route('onboarding.organization'));

        $this->assertSame(1, PaymentMethod::acrossOrganizations()->count());
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'status' => 'draft']);
    }
}
