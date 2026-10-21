<?php

namespace Tests\Feature\PublicPages;

use App\Bookings\BookingPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-008 section A: "Tanya via WhatsApp" on the public trip page, and the shared
 * builder's invalid-number guard on the seller and booking pages (AC-A9/D3).
 */
class TripWhatsAppTest extends TestCase
{
    use BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip', ['whatsapp' => '+6281277881020']);
    }

    public function test_ac_a1_the_trip_page_carries_the_seller_whatsapp_link(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M'], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24');

        $url = $this->tripProps('kencana', $trip->slug)['organization']['whatsapp_url'];

        $this->assertStringStartsWith('https://wa.me/6281277881020?text=', $url);
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.trip', ['name' => 'Kencana Trip', 'trip' => 'Karimunjawa 3H2M'])),
            $url,
        );
    }

    public function test_ac_a3_trip_only_message_when_no_departure_is_bookable(): void
    {
        // Request-a-date trip: no departures section, trip-only message.
        $request = $this->tripFor($this->a, ['name' => 'Private Komodo', 'booking_mode' => 'request_date', 'type' => 'private'], published: true);
        $requestProps = $this->tripProps('kencana', $request->slug);

        $this->assertSame([], $requestProps['departures']);
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.trip', ['name' => 'Kencana Trip', 'trip' => 'Private Komodo'])),
            $requestProps['organization']['whatsapp_url'],
        );

        // Scheduled trip with only a closed departure: trip-only on the org, null on the row.
        $scheduled = $this->tripFor($this->a, ['name' => 'Dieng Sunrise'], published: true);
        $this->departureFor($this->a, $scheduled, '2024-10-24', ['status' => 'closed', 'closed_reason' => 'manual']);
        $scheduledProps = $this->tripProps('kencana', $scheduled->slug);

        $this->assertSame('closed', $scheduledProps['departures'][0]['state']);
        $this->assertNull($scheduledProps['departures'][0]['whatsapp_url']);
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.trip', ['name' => 'Kencana Trip', 'trip' => 'Dieng Sunrise'])),
            $scheduledProps['organization']['whatsapp_url'],
        );
    }

    public function test_ac_a4_dated_message_on_a_bookable_departure(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M'], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24');

        $props = $this->tripProps('kencana', $trip->slug);
        $departure = $props['departures'][0];
        $expected = __('whatsapp.trip_dated', [
            'name' => 'Kencana Trip',
            'trip' => 'Karimunjawa 3H2M',
            'date' => BookingPresenter::dates($departure['start_date'], $departure['end_date'], $props['context']['today']),
        ]);

        $this->assertSame('bookable', $departure['state']);
        $this->assertSame('https://wa.me/6281277881020?text='.rawurlencode($expected), $departure['whatsapp_url']);
        // The dated text names the departure (same text as the booking page, AC-A4).
        $this->assertStringContainsString('keberangkatan', rawurldecode(substr($departure['whatsapp_url'], strpos($departure['whatsapp_url'], '?text=') + 6)));
    }

    public function test_ac_a6_an_invalid_number_shows_no_whatsapp_url_anywhere(): void
    {
        $this->a->organization->update(['whatsapp' => '-']);
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M'], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24');

        $props = $this->tripProps('kencana', $trip->slug);

        $this->assertNull($props['organization']['whatsapp_url']);
        $this->assertNull($props['departures'][0]['whatsapp_url']);
        // The booking CTA is untouched.
        $this->assertSame(url('/kencana/'.$trip->slug.'/pesan'), $props['urls']['book']);
    }

    public function test_ac_a7_special_characters_in_names_are_encoded_once(): void
    {
        $this->a->organization->update(['name' => 'A & B? #1']);
        $trip = $this->tripFor($this->a, ['name' => 'Trip "Spesial" é 😀'], published: true);

        $url = $this->tripProps('kencana', $trip->slug)['organization']['whatsapp_url'];
        $encoded = substr($url, strpos($url, '?text=') + 6);

        // Encoded once: one decode restores the interpolated text; no literal "%25".
        $this->assertSame(__('whatsapp.trip', ['name' => 'A & B? #1', 'trip' => 'Trip "Spesial" é 😀']), rawurldecode($encoded));
        $this->assertStringNotContainsString('%25', $encoded);
    }

    public function test_ac_a9_d3_the_seller_and_booking_pages_also_hide_an_invalid_number(): void
    {
        $this->a->organization->update(['whatsapp' => '-']);
        $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M'], published: true);

        $this->assertNull($this->sellerProps()['organization']['whatsapp_url']);
    }

    public function test_ac_a10_a_trip_page_carries_only_its_own_sellers_number(): void
    {
        $b = $this->seller('bahari', 'Bahari Tour', ['whatsapp' => '+6289999988888']);
        $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M'], published: true);
        $bTrip = $this->tripFor($b, ['name' => 'Menjangan'], published: true);
        $this->departureFor($b, $bTrip, '2024-10-24');

        $response = $this->get('/bahari/'.$bTrip->slug);
        $content = $response->assertOk()->getContent();

        $this->assertStringContainsString('6289999988888', $content);
        $this->assertStringNotContainsString('6281277881020', $content);

        $props = $this->tripProps('bahari', $bTrip->slug);
        $this->assertStringContainsString('wa.me/6289999988888', $props['organization']['whatsapp_url']);
        $this->assertStringContainsString('wa.me/6289999988888', $props['departures'][0]['whatsapp_url']);
    }
}
