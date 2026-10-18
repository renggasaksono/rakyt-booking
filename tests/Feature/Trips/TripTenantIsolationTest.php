<?php

namespace Tests\Feature\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-003 AC-40 (ADR-0004, F-009 A2/A3/A9): seller B gets a 404 for every action
 * on seller A's trips, departures and photos, and nothing changes. One test per action.
 */
class TripTenantIsolationTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    private Trip $trip;

    private TripDeparture $departure;

    private TripPhoto $photo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        Storage::fake(config('rakyt.media.public_disk'));
        $this->withPaymentMethod();

        [$this->a, $this->b] = $this->createTenants();
        $this->trip = $this->tripFor($this->a, ['name' => 'Rahasia A', 'base_price' => 1_250_000]);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
        $this->photo = $this->asTenant($this->a, fn () => TripPhoto::create([
            'trip_id' => $this->trip->id,
            'path' => "org/{$this->a->organization->public_id}/trips/{$this->trip->public_id}/a.jpg",
            'position' => 1,
        ]));
    }

    private function assertUnchanged(): void
    {
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'name' => 'Rahasia A', 'status' => 'draft', 'base_price' => 1_250_000]);
        $this->assertDatabaseHas('trip_departures', ['id' => $this->departure->id, 'capacity' => 16, 'price_override' => null, 'status' => 'open']);
        $this->assertDatabaseHas('trip_photos', ['id' => $this->photo->id, 'position' => 1]);
        $this->assertSame(1, TripDeparture::acrossOrganizations()->where('organization_id', $this->a->id())->count());
        $this->assertSame(1, TripPhoto::acrossOrganizations()->where('organization_id', $this->a->id())->count());
        $this->assertDatabaseCount('audit_events', 0);
    }

    private function assertIntruderGets404(string $method, string $uri, array $payload = []): void
    {
        $this->assertCrossTenantNotFound($this->b, [[$method, $uri, $payload]], fn () => $this->assertUnchanged());
    }

    public function test_ac_40_view(): void
    {
        $this->assertIntruderGets404('GET', route('trips.edit', $this->trip));
    }

    public function test_ac_40_edit(): void
    {
        $this->assertIntruderGets404('PUT', route('trips.update', $this->trip), ['name' => 'Diubah B', 'base_price' => 1000]);
    }

    public function test_ac_40_autosave(): void
    {
        $this->assertIntruderGets404('PATCH', route('trips.autosave', $this->trip), ['name' => 'Diubah B']);
    }

    public function test_ac_40_publish(): void
    {
        $this->assertIntruderGets404('POST', route('trips.publish', $this->trip));
    }

    public function test_ac_40_unpublish(): void
    {
        $this->asTenant($this->a, fn () => Trip::query()->whereKey($this->trip->id)->update(['status' => 'published']));

        $this->actingAsTenant($this->b)->postJson(route('trips.unpublish', $this->trip))->assertNotFound();

        $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'status' => 'published']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_ac_40_delete_trip(): void
    {
        $this->assertIntruderGets404('DELETE', route('trips.destroy', $this->trip));
    }

    public function test_ac_40_upload_photo(): void
    {
        $this->assertIntruderGets404('POST', route('trips.photos.store', $this->trip), ['photo' => UploadedFile::fake()->image('b.jpg', 10, 10)]);
        $this->assertSame([], Storage::disk(config('rakyt.media.public_disk'))->allFiles());
    }

    public function test_ac_40_reorder_photos(): void
    {
        $this->assertIntruderGets404('PUT', route('trips.photos.reorder', $this->trip), ['photos' => [$this->photo->public_id]]);
    }

    public function test_ac_40_remove_photo(): void
    {
        $this->assertIntruderGets404('DELETE', route('trips.photos.destroy', [$this->trip, $this->photo]));
    }

    public function test_ac_40_add_departure(): void
    {
        $this->assertIntruderGets404('POST', route('trips.departures.store', $this->trip), ['start_date' => '2024-10-31', 'capacity' => 10]);
    }

    public function test_ac_40_edit_departure(): void
    {
        $this->assertIntruderGets404('PUT', route('trips.departures.update', [$this->trip, $this->departure]), ['capacity' => 99]);
    }

    public function test_ac_40_quick_edit(): void
    {
        $this->assertIntruderGets404('PATCH', route('trips.departures.quick', [$this->trip, $this->departure]), ['price' => 1000, 'capacity' => 99, 'status' => 'closed']);
    }

    public function test_ac_40_delete_departure(): void
    {
        $this->assertIntruderGets404('DELETE', route('trips.departures.destroy', [$this->trip, $this->departure]));
    }

    public function test_ac_40_copy_from(): void
    {
        $this->assertIntruderGets404('GET', route('trips.departures.copy', $this->trip));
    }

    public function test_ac_40_departure_of_a_foreign_trip_under_an_own_trip_url(): void
    {
        $own = $this->tripFor($this->b);

        $this->assertIntruderGets404('PUT', route('trips.departures.update', [$own, $this->departure]), ['capacity' => 99]);
        $this->assertIntruderGets404('PATCH', route('trips.departures.quick', [$own, $this->departure]), ['price' => 1000, 'capacity' => 99, 'status' => 'closed']);
        $this->assertIntruderGets404('DELETE', route('trips.photos.destroy', [$own, $this->photo]));
    }

    public function test_ac_40_payload_referencing_foreign_photo_ids_is_rejected(): void
    {
        $own = $this->tripFor($this->b);
        $ownPhoto = $this->asTenant($this->b, fn () => TripPhoto::create(['trip_id' => $own->id, 'path' => 'org/b/trips/b/b.jpg', 'position' => 1]));

        $this->assertCrossTenantPayloadRejected(
            $this->b,
            'PUT',
            route('trips.photos.reorder', $own),
            ['photos' => [$this->photo->public_id, $ownPhoto->public_id]],
            'photos.0',
            fn () => $this->assertUnchanged(),
        );
    }

    public function test_ac_40_payload_organization_or_trip_ids_never_write_to_another_seller(): void
    {
        $own = $this->tripFor($this->b);

        $this->actingAsTenant($this->b)->postJson(route('trips.departures.store', $own), [
            'start_date' => '2024-10-31',
            'capacity' => 10,
            'trip_id' => $this->trip->id,
            'organization_id' => $this->a->id(),
        ])->assertRedirect();

        $this->assertDatabaseHas('trip_departures', ['trip_id' => $own->id, 'organization_id' => $this->b->id()]);
        $this->assertSame(1, TripDeparture::acrossOrganizations()->where('trip_id', $this->trip->id)->count());
    }

    public function test_ac_40_lists_only_show_own_trips_and_departures(): void
    {
        $this->actingAsTenant($this->b)->get(route('trips.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('trips', 0));
        $this->actingAsTenant($this->b)->get(route('departures.upcoming'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('departures', 0));
    }

    public function test_ac_40_owner_still_reaches_their_own_trip(): void
    {
        $this->actingAsTenant($this->a)->get(route('trips.edit', $this->trip))->assertOk();
    }

    public function test_r16_malformed_ids_in_nested_urls_are_404_not_500(): void
    {
        foreach (['abc', str_repeat('z', 300), '01JAAAAAAAAAAAAAAAAAAAAAAA'] as $bad) {
            $this->actingAsTenant($this->a)->getJson('/trip/'.rawurlencode($bad))->assertNotFound();
            $this->actingAsTenant($this->a)->putJson('/trip/'.$this->trip->public_id.'/keberangkatan/'.rawurlencode($bad), ['capacity' => 2])->assertNotFound();
            $this->actingAsTenant($this->a)->deleteJson('/trip/'.$this->trip->public_id.'/foto/'.rawurlencode($bad))->assertNotFound();
        }

        // NUL bytes and invalid UTF-8 never reach the database: a bad request or not found, never a 500.
        foreach (['x%00y', '%FF', 'a%C3'] as $raw) {
            $this->assertContains($this->actingAsTenant($this->a)->getJson('/trip/'.$raw)->status(), [400, 404], $raw);
            $this->assertContains($this->actingAsTenant($this->a)->putJson('/trip/'.$this->trip->public_id.'/keberangkatan/'.$raw, ['capacity' => 2])->status(), [400, 404], $raw);
        }
    }

    public function test_guests_are_sent_home_and_users_without_an_organization_see_404(): void
    {
        $this->get(route('trips.index'))->assertRedirect(route('home'));
        $this->getJson(route('trips.edit', $this->trip))->assertUnauthorized();

        $this->actingAs(User::factory()->create())->get(route('trips.index'))->assertNotFound();
    }
}
