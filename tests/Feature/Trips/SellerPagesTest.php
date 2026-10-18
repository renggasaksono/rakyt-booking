<?php

namespace Tests\Feature\Trips;

use App\Policies\TripPolicy;
use App\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 seller pages: minimal layout, empty states (AC-42), Pengaturan →
 * Pemesanan (Build order, Q6). Copy comes from lang/id (ADR-0007).
 */
class SellerPagesTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    public function test_the_seller_layout_shows_trips_and_settings(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('trips.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Trips/Index')
                ->has('seller.nav', 2)
                ->where('seller.nav.0.label', 'Trip & keberangkatan')
                ->where('seller.nav.0.url', route('trips.index'))
                ->where('seller.nav.0.active', true)
                // F-001 F3: Pengaturan hosts Profil usaha and Akun.
                ->where('seller.nav.1.url', route('settings.index'))
                ->where('seller.nav.1.active', false)
                ->where('seller.close_label', 'Tutup'));
    }

    public function test_listing_and_creating_trips_is_authorised_through_the_policy(): void
    {
        [$a] = $this->createTenants();
        Gate::before(fn ($user, string $ability) => in_array($ability, ['viewAny', 'create'], true) ? false : null);

        $this->actingAsTenant($a)->get(route('trips.index'))->assertForbidden();
        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Bromo'])->assertForbidden();
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_the_trip_policy_allows_list_and_create_only_with_a_current_organization(): void
    {
        [$a] = $this->createTenants();
        $policy = new TripPolicy;

        $this->asTenant($a, function () use ($policy, $a) {
            $this->assertTrue($policy->viewAny($a->owner)->allowed());
            $this->assertTrue($policy->create($a->owner)->allowed());
        });

        app(CurrentOrganization::class)->set(null);
        $this->assertSame(404, $policy->viewAny($a->owner)->status());
        $this->assertSame(404, $policy->create($a->owner)->status());
    }

    public function test_ac_42_no_trips_shows_the_empty_state_with_new_trip(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('trips.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trips', 0)
                ->where('copy.index.empty_title', 'Belum ada trip')
                ->where('copy.index.new_trip', '+ Trip baru')
                ->where('urls.create', route('trips.create')));
    }

    public function test_ac_42_a_scheduled_trip_without_departures_shows_the_empty_state(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Trips/Edit')
                ->has('departures.upcoming', 0)
                ->has('departures.past', 0)
                ->where('copy.departures.empty', 'Belum ada keberangkatan')
                ->where('copy.departures.add', '+ Tambah keberangkatan'));
    }

    public function test_the_trip_list_shows_status_price_and_next_departure(): void
    {
        $this->freezeClock();
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['name' => 'Karimunjawa'], published: true);
        $this->departureFor($a, $trip, '2024-10-24', ['price_override' => 1_150_000]);

        $this->actingAsTenant($a)->get(route('trips.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('trips.0.name', 'Karimunjawa')
                ->where('trips.0.status', 'published')
                ->where('trips.0.from_price', 1_150_000)
                ->where('trips.0.next_departure.start_date', '2024-10-24')
                ->where('trips.0.edit_url', route('trips.edit', $trip)));
    }

    public function test_booking_settings_change_the_default_deadline_preset(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('settings.booking.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Booking')
                ->where('settings.default_booking_deadline_preset', 'h3')
                ->where('options', ['h1', 'h3', 'h7'])
                ->where('urls.back', route('settings.index'))
                ->where('backLabel', '← Pengaturan'));

        $this->actingAsTenant($a)->put(route('settings.booking.update'), ['default_booking_deadline_preset' => 'h7'])
            ->assertSessionHas('toast', 'Tersimpan');

        $this->assertDatabaseHas('organizations', ['id' => $a->id(), 'default_booking_deadline_preset' => 'h7']);
    }

    public function test_booking_settings_reject_unknown_presets(): void
    {
        [$a] = $this->createTenants();

        foreach (['same_day', 'custom', 'h30'] as $bad) {
            $this->actingAsTenant($a)->putJson(route('settings.booking.update'), ['default_booking_deadline_preset' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('default_booking_deadline_preset');
        }
    }

    public function test_booking_settings_only_touch_the_current_organization(): void
    {
        [$a, $b] = $this->createTenants();

        $this->actingAsTenant($b)->put(route('settings.booking.update'), ['default_booking_deadline_preset' => 'h1', 'organization_id' => $a->id()]);

        $this->assertDatabaseHas('organizations', ['id' => $a->id(), 'default_booking_deadline_preset' => 'h3']);
        $this->assertDatabaseHas('organizations', ['id' => $b->id(), 'default_booking_deadline_preset' => 'h1']);
    }

    public function test_guests_cannot_reach_the_settings(): void
    {
        $this->get(route('settings.booking.edit'))->assertRedirect(route('login'));
        $this->putJson(route('settings.booking.update'), ['default_booking_deadline_preset' => 'h1'])->assertUnauthorized();
    }

    public function test_the_organization_columns_default_to_jakarta_and_h3(): void
    {
        [$a] = $this->createTenants();

        $this->assertDatabaseHas('organizations', ['id' => $a->id(), 'timezone' => 'Asia/Jakarta', 'default_booking_deadline_preset' => 'h3']);
    }

    public function test_every_editor_copy_key_used_by_the_pages_exists_in_lang(): void
    {
        $copy = __('trips');

        foreach (['index.title', 'editor.saved_at', 'editor.unsaved', 'editor.retry', 'editor.leave_confirm', 'checklist.publish', 'checklist.remaining', 'quick_edit.floor', 'quick_edit.reopen', 'deadline.confirm', 'calendar.months_short', 'status.open', 'status.closed', 'status.full', 'status.past'] as $key) {
            $this->assertNotNull(data_get($copy, $key), "lang/id/trips.php is missing {$key}");
        }
    }
}
