<?php

namespace Tests\Feature\PublicPages;

use App\Models\Trip;
use App\WhatsApp\WhatsAppLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-004 (Option B): the "Ajukan tanggal →" CTA is a WhatsApp handoff
 * (`whatsapp.date_request`) built through the shared WhatsAppLink::to() builder.
 * AC-2/3/6 also exercised in TripPageTest; AC-8 (one gold button) in
 * PublicPageDesignTest. The clock is Thursday 2024-10-10 10:00 WIB.
 */
class RequestDateHandoffTest extends TestCase
{
    use BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip', ['whatsapp' => '+6281277881020']);
    }

    private function requestDateTrip(string $name = 'Private Komodo'): Trip
    {
        return $this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create([
            'name' => $name,
            'base_price' => 3_000_000,
            'duration_label' => null,
            'duration_days' => null,
            'duration_nights' => null,
        ]));
    }

    public function test_ac_1_a_dateless_request_date_trip_is_published_and_discoverable(): void
    {
        $trip = $this->requestDateTrip();

        // It appears on the seller page as a request-date card...
        $card = collect($this->sellerProps()['trips'])->firstWhere('slug', $trip->slug);
        $this->assertSame('request_date', $card['state']);
        $this->assertNull($card['next_start_date']);

        // ...and has its own trip page with no departure list (F-003 checklist n/4
        // regression is covered by Trips/PublishTest::test_ac_9_request_date_trips_have_four_items).
        $props = $this->tripProps('kencana', $trip->slug);
        $this->assertSame('request_date', $props['trip']['booking_mode']);
        $this->assertSame([], $props['departures']);
    }

    public function test_ac_2_request_date_trip_carries_the_wa_me_handoff_url(): void
    {
        $trip = $this->requestDateTrip('Private Komodo');

        $url = $this->tripProps('kencana', $trip->slug)['urls']['request_date'];

        $this->assertStringStartsWith('https://wa.me/6281277881020?text=', $url);
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.date_request', ['name' => 'Kencana Trip', 'trip' => 'Private Komodo'])),
            $url,
        );
    }

    public function test_ac_3_message_content_is_the_date_request_body_encoded_once(): void
    {
        $this->a->organization->update(['name' => 'A & B? #1']);
        $trip = $this->requestDateTrip('Trip "Spesial" é 😀');

        $url = $this->tripProps('kencana', $trip->slug)['urls']['request_date'];
        $encoded = substr($url, strpos($url, '?text=') + 6);

        // One decode restores the fully interpolated body; no double-encoded "%25".
        $this->assertSame(
            __('whatsapp.date_request', ['name' => 'A & B? #1', 'trip' => 'Trip "Spesial" é 😀']),
            rawurldecode($encoded),
        );
        $this->assertStringContainsString('saya mau ajukan tanggal untuk', rawurldecode($encoded));
        $this->assertStringNotContainsString('%25', $encoded);
        $this->assertStringNotContainsString(':name', rawurldecode($encoded));
        $this->assertStringNotContainsString(':trip', rawurldecode($encoded));
    }

    public function test_ac_4_the_url_is_produced_by_the_shared_builder(): void
    {
        $trip = $this->requestDateTrip('Private Komodo');

        $expected = WhatsAppLink::to($this->a->organization, __('whatsapp.date_request', [
            'name' => $this->a->organization->name,
            'trip' => $trip->name,
        ]));

        $this->assertSame($expected, $this->tripProps('kencana', $trip->slug)['urls']['request_date']);
    }

    public function test_ac_5_an_invalid_number_hides_the_handoff_cta(): void
    {
        $this->a->organization->update(['whatsapp' => '-']);
        $trip = $this->requestDateTrip();

        $props = $this->tripProps('kencana', $trip->slug);
        $this->assertNull($props['urls']['request_date']);

        // No wa.me URL leaks into the server-rendered page data.
        $content = $this->get('/kencana/'.$trip->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('wa.me/?text=', $content);
        $this->assertStringNotContainsString('wa.me/6281277881020', $content);
    }

    public function test_ac_6_scheduled_trips_reuse_the_same_handoff_url(): void
    {
        // A scheduled trip with a bookable departure: the ink "other date" link still
        // uses the handoff; and with no bookable departure the gold CTA uses it too.
        $bookable = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $this->departureFor($this->a, $bookable, '2024-10-24');

        $full = $this->tripFor($this->a, ['name' => 'Dieng'], published: true);
        $this->departureFor($this->a, $full, '2024-10-24', seatsTaken: 16);

        foreach ([$bookable, $full] as $trip) {
            $this->assertSame(
                'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.date_request', ['name' => 'Kencana Trip', 'trip' => $trip->name])),
                $this->tripProps('kencana', $trip->slug)['urls']['request_date'],
            );
        }
    }

    public function test_ac_7_the_dead_ajukan_tanggal_route_is_gone(): void
    {
        $trip = $this->requestDateTrip();

        // urls.request_date is a wa.me URL, never the old in-app path.
        $url = $this->tripProps('kencana', $trip->slug)['urls']['request_date'];
        $this->assertStringNotContainsString('/ajukan-tanggal', (string) $url);

        // No in-app request-form route answers that path.
        $this->get('/kencana/'.$trip->slug.'/ajukan-tanggal')->assertNotFound();
    }

    public function test_ac_9_a_trip_page_carries_only_its_own_sellers_number(): void
    {
        $b = $this->seller('bahari', 'Bahari Tour', ['whatsapp' => '+6289999988888']);
        $this->requestDateTrip('Private A');
        $bTrip = $this->asTenant($b, fn () => Trip::factory()->requestDate()->published()->create([
            'name' => 'Private B', 'base_price' => 3_000_000,
            'duration_label' => null, 'duration_days' => null, 'duration_nights' => null,
        ]));

        $content = $this->get('/bahari/'.$bTrip->slug)->assertOk()->getContent();
        $this->assertStringContainsString('6289999988888', $content);
        $this->assertStringNotContainsString('6281277881020', $content);

        $this->assertStringContainsString(
            'wa.me/6289999988888',
            $this->tripProps('bahari', $bTrip->slug)['urls']['request_date'],
        );
    }

    public function test_ac_10_there_is_no_in_app_date_request_entity_or_route(): void
    {
        // Out (P1): no table, no model, no request-form route — only a plain wa.me link.
        $this->assertFalse(Schema::hasTable('date_requests'));
        $this->assertFalse(class_exists('App\\Models\\DateRequest'));

        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('ajukan-tanggal', $route->uri());
            $this->assertStringNotContainsString('date-request', $route->uri());
        }

        // The reserved seller → customer "Balas" key stays empty (no inbox reply flow).
        $this->assertSame('', __('whatsapp.date_reply'));
    }
}
