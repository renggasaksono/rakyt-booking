<?php

namespace Tests\Feature\PublicPages;

use App\Http\Middleware\EnsureOnboardingFinished;
use App\Models\PaymentMethod;
use App\Models\Trip;
use App\PublicPages\Middleware\ResolvePublicOrganization;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Middleware\EnsureCurrentOrganization;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use App\Trips\TripStatus;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-002 AC-5–AC-8: drafts and other sellers' data never reach a public page.
 * Closes F-009 A5, F-001 D7 and the public half of F-001 T3.
 */
class PublicTenancyTest extends TestCase
{
    use BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip');
        $this->b = $this->seller('bahari', 'Bahari Tour');
    }

    public function test_ac_5_a_draft_trip_is_404_for_everyone_and_listed_nowhere(): void
    {
        $draft = $this->tripFor($this->a, ['name' => 'Rahasia Draf']);
        $this->photoFor($this->a, $draft);
        $unpublished = $this->tripFor($this->a, ['name' => 'Pernah Terbit']);
        Trip::acrossOrganizations()->whereKey($unpublished->id)->update(['published_at' => now()->subDay()]);
        $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);

        foreach ([$draft, $unpublished] as $trip) {
            $this->assertSame(TripStatus::Draft, $this->freshTrip($trip)->status);

            // Guest, a seller of another organization, and the owner.
            $this->get("/kencana/{$trip->slug}")->assertNotFound()->assertDontSee($trip->name);
            $this->actingAsTenant($this->b)->get("/kencana/{$trip->slug}")->assertNotFound();
            $this->actingAsTenant($this->a)->get("/kencana/{$trip->slug}")->assertNotFound();
            auth()->logout();
        }

        $page = $this->get('/kencana')->assertOk();
        $page->assertDontSee('Rahasia Draf')->assertDontSee('Pernah Terbit')->assertDontSee('rahasia-draf');
        $this->assertSame(['karimunjawa'], array_column($this->sellerProps()['trips'], 'slug'));
    }

    public function test_ac_5_a_trip_unpublished_by_the_seller_disappears(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $this->get('/kencana/karimunjawa')->assertOk();

        $this->actingAsTenant($this->a)->post(route('trips.unpublish', $trip))->assertRedirect();
        auth()->logout();

        $this->get('/kencana/karimunjawa')->assertNotFound();
        $this->assertSame([], $this->sellerProps()['trips']);
    }

    public function test_ac_6_another_sellers_trip_is_404_under_this_slug(): void
    {
        $this->tripFor($this->b, ['name' => 'Menjangan'], published: true);

        $this->get('/kencana/menjangan')->assertNotFound()->assertDontSee('Menjangan');
        $this->get('/bahari/menjangan')->assertOk();
    }

    public function test_ac_6_the_same_trip_slug_resolves_per_seller(): void
    {
        $this->tripFor($this->a, ['name' => 'Menjangan', 'base_price' => 900_000], published: true);
        $this->tripFor($this->b, ['name' => 'Menjangan', 'base_price' => 1_500_000], published: true);

        $a = $this->tripProps('kencana', 'menjangan');
        $b = $this->tripProps('bahari', 'menjangan');

        $this->assertSame([900_000, 'Kencana Trip'], [$a['trip']['from_price'], $a['organization']['name']]);
        $this->assertSame([1_500_000, 'Bahari Tour'], [$b['trip']['from_price'], $b['organization']['name']]);
    }

    public function test_ac_f009_a5_public_urls_never_name_another_sellers_or_a_draft_record(): void
    {
        $bTrip = $this->tripFor($this->b, ['name' => 'Menjangan'], published: true);
        $bDeparture = $this->departureFor($this->b, $bTrip);
        $aDraft = $this->tripFor($this->a, ['name' => 'Draf A']);

        foreach ([
            '/kencana/menjangan',
            '/kencana/'.$bTrip->public_id,
            '/kencana/'.$bTrip->id,
            '/kencana/'.$bDeparture->public_id,
            '/kencana/'.$aDraft->slug,
            '/kencana/'.$aDraft->public_id,
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_ac_7_a_signed_in_seller_sees_another_seller_exactly_as_a_guest(): void
    {
        $this->tripFor($this->a, ['name' => 'Trip Milik A'], published: true);
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create(['account_number' => '1234567890', 'account_holder' => 'Pemilik A']));
        $bTrip = $this->tripFor($this->b, ['name' => 'Menjangan'], published: true);
        $this->departureFor($this->b, $bTrip);

        $guestSeller = $this->sellerProps('bahari');
        $guestTrip = $this->tripProps('bahari', 'menjangan');

        $this->actingAsTenant($this->a);
        $sellerResponse = $this->get('/bahari')->assertOk();
        $tripResponse = $this->get('/bahari/menjangan')->assertOk();

        $this->assertSame($guestSeller, $this->propsOf($sellerResponse, 'Public/Seller'));
        $this->assertSame($guestTrip, $this->propsOf($tripResponse, 'Public/Trip'));

        foreach ([$sellerResponse, $tripResponse] as $response) {
            foreach (['Kencana Trip', 'kencana', 'Trip Milik A', '1234567890', 'Pemilik A', $this->a->organization->public_id, $this->a->owner->name, $this->a->owner->email] as $leak) {
                $response->assertDontSee($leak, false);
            }
        }

        // The current organization came from the slug, not the session.
        $this->assertSame($this->b->id(), app(CurrentOrganization::class)->id());
    }

    public function test_ac_7_public_routes_do_not_run_the_seller_middleware(): void
    {
        // The HTTP kernel registers the middleware groups and priority with the router.
        $this->app->make(Kernel::class);

        foreach (['public.seller', 'public.trip'] as $name) {
            $middleware = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName($name));

            $this->assertContains(ResolvePublicOrganization::class, $middleware);
            $this->assertNotContains(EnsureCurrentOrganization::class, $middleware);
            $this->assertNotContains(EnsureOnboardingFinished::class, $middleware);
            $this->assertNotContains(Authenticate::class, $middleware);

            // The slug's organization replaces the session's, before route binding.
            $this->assertGreaterThan(array_search(ResolveCurrentOrganization::class, $middleware, true), array_search(ResolvePublicOrganization::class, $middleware, true));
        }
    }

    public function test_ac_8_only_public_fields_leave_the_server(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa', 'short_description' => 'Pulau'], published: true);
        $this->photoFor($this->a, $trip);
        $this->departureFor($this->a, $trip, '2024-10-24', seatsTaken: 3);
        $this->departureFor($this->a, $trip, '2024-10-31', ['status' => 'closed', 'closed_reason' => 'manual']);
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create(['account_number' => '9876543210']));
        $this->tripFor($this->a, ['name' => 'Draf Tersembunyi']);

        $seller = $this->sellerProps();
        $page = $this->tripProps('kencana', 'karimunjawa');

        $this->assertSame(['calendar', 'context', 'copy', 'errors', 'organization', 'title', 'trips', 'urls'], $this->sortedKeys($seller));
        $this->assertSame(['bio', 'city', 'logo_url', 'name', 'share_url', 'slug', 'whatsapp_url'], $this->sortedKeys($seller['organization']));
        $this->assertSame(['cover', 'from_price', 'name', 'next_start_date', 'seats_left', 'slug', 'state', 'url'], $this->sortedKeys($seller['trips'][0]));
        $this->assertSame(['src', 'srcset'], $this->sortedKeys($seller['trips'][0]['cover']));
        $this->assertSame(['today'], $this->sortedKeys($seller['context']));
        $this->assertSame(['home'], $this->sortedKeys($seller['urls']));

        $this->assertSame(['calendar', 'context', 'copy', 'departures', 'errors', 'organization', 'rules', 'title', 'trip', 'urls'], $this->sortedKeys($page));
        $this->assertSame(['name', 'slug', 'whatsapp_url'], $this->sortedKeys($page['organization']));
        $this->assertSame([
            'booking_mode', 'duration', 'exclusions', 'from_price', 'inclusions', 'itinerary', 'map_url', 'meeting_point',
            'meeting_time', 'name', 'photos', 'short_description', 'slug', 'terms', 'type', 'type_label',
        ], $this->sortedKeys($page['trip']));
        $this->assertSame(['capacity', 'deadline', 'deadline_preset', 'end_date', 'price', 'public_id', 'seats_left', 'start_date', 'state', 'whatsapp_url'], $this->sortedKeys($page['departures'][0]));
        $this->assertSame(['now_local', 'today'], $this->sortedKeys($page['context']));
        $this->assertSame(['low_seats', 'shown', 'urgent_days'], $this->sortedKeys($page['rules']));
        $this->assertSame(['book', 'home', 'request_date', 'seller'], $this->sortedKeys($page['urls']));

        // No ids, organization or seat internals anywhere in the props.
        foreach ([$seller, $page] as $props) {
            $keys = array_map(fn ($key) => last(explode('.', $key)), array_keys(Arr::dot(Arr::except($props, ['copy', 'calendar']))));
            $this->assertEmpty(array_intersect($keys, ['id', 'organization_id', 'public_id_org', 'seats_taken', 'email', 'members', 'payment_methods', 'audit', 'status', 'timezone', 'plan', 'whatsapp']));
        }

        // Departures are identified by their public id only (a lower-case ULID).
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $page['departures'][0]['public_id']);

        foreach (['/kencana', '/kencana/karimunjawa'] as $path) {
            $response = $this->get($path)
                ->assertDontSee('9876543210')
                ->assertDontSee('Draf Tersembunyi')
                ->assertDontSee($this->a->owner->email)
                ->assertDontSee('seats_taken');

            // Photo URLs carry the organization's public id in their storage path
            // (ADR-0015); it appears nowhere else.
            $id = $this->a->organization->public_id;
            $outsideMedia = str_replace(["org/{$id}/", "org\\/{$id}\\/"], '', $response->getContent());
            $this->assertStringNotContainsString($this->a->organization->public_id, $outsideMedia);
        }
    }

    public function test_ac_t3_a_draft_of_a_never_appears_on_bs_page(): void
    {
        $this->tripFor($this->a, ['name' => 'Draf A']);
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create(['account_number' => '5550001111']));

        $this->get('/bahari')->assertOk()->assertDontSee('Draf A')->assertDontSee('5550001111');
        $this->get('/bahari/draf-a')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $array
     * @return list<string>
     */
    private function sortedKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys);

        return $keys;
    }
}
