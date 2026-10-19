<?php

namespace Tests\Feature\Bookings;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\TripStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-005 section E: the customer booking page /{slug}/pesanan/{token} (top
 * half of mockups 1d/2e). The clock is Thursday 2024-10-10 10:00 WIB.
 */
class BookingPageTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    private function book(array $overrides = []): Booking
    {
        $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($this->departure, $overrides))->assertStatus(303);

        return $this->latestBooking();
    }

    private function pageOf(Booking $booking): array
    {
        return $this->bookingPageProps('kencana', $booking->access_token);
    }

    public function test_ac_29_an_awaiting_booking_shows_reference_status_hold_and_summary(): void
    {
        $booking = $this->book(['participants' => 2]);

        $props = $this->pageOf($booking);

        $this->assertSame([
            'reference' => 'RKB-KNC-1024-0001',
            'status' => 'awaiting_payment',
            'cancel_reason' => null,
            'participants' => 2,
            'unit_price' => 1_250_000,
            'total_amount' => 2_500_000,
            'customer_name' => 'Rina Wulandari',
            'customer_phone' => '+6281234567890',
            'hold_expires_at' => '2024-10-10T05:00:00Z',
        ], $props['booking']);
        $this->assertSame(['name' => 'Karimunjawa 3H2M', 'url' => url('/kencana/karimunjawa-3h2m')], $props['trip']);
        $this->assertSame(['start_date' => '2024-10-24', 'end_date' => '2024-10-26'], $props['departure']);
        $this->assertSame(['point' => 'Pelabuhan Jepara', 'time' => '06:00'], $props['meeting']);

        $copy = $props['copy'];
        $this->assertSame('Pesanan terkirim', $copy['page']['sent']);
        $this->assertSame('Menunggu pembayaran', $copy['status']['awaiting_payment']);
        $this->assertSame([':h j :m m', ':m m', '< 1 m'], array_values($copy['page']['countdown']));
        $this->assertSame('Kursi ditahan s.d. :time. Setelah itu kursi dilepas otomatis.', $copy['page']['hold_until']);
        $this->assertSame('Kursi ditahan s.d. besok :time. Setelah itu kursi dilepas otomatis.', $copy['page']['hold_until_tomorrow']);
        $this->assertSame([':n peserta × :price', 'Total :amount'], [$copy['page']['line'], $copy['page']['total']]);
    }

    public function test_ac_30_the_page_carries_the_server_clock_and_time_zone(): void
    {
        $booking = $this->book();
        $this->travelTo(CarbonImmutable::now()->addMinutes(8));

        $this->assertSame(['now' => '2024-10-10T03:08:00Z', 'timezone' => 'Asia/Jakarta'], $this->pageOf($booking)['context']);
    }

    public function test_ac_31_an_expired_hold_shows_the_cancelled_state_with_a_link_while_the_trip_is_published(): void
    {
        $released = $this->bookingFor($this->a, $this->departure, 2, BookingStatus::Cancelled);
        $late = $this->expiredHoldFor($this->a, $this->departure, 1);

        $props = $this->pageOf($released);
        $this->assertSame(['cancelled', 'hold_expired'], [$props['booking']['status'], $props['booking']['cancel_reason']]);
        $this->assertSame(url('/kencana/karimunjawa-3h2m'), $props['trip']['url']);
        $this->assertSame('Waktu pembayaran habis. Kursi sudah dilepas.', $props['copy']['page']['expired']);
        $this->assertSame('Pesan lagi', $props['copy']['page']['book_again']);

        // Past its hold before the job ran: the page decides from the server clock (AC-30).
        $props = $this->pageOf($late);
        $this->assertSame('awaiting_payment', $props['booking']['status']);
        $this->assertLessThan($props['context']['now'], $props['booking']['hold_expires_at']);

        $this->asTenant($this->a, fn () => $this->trip->forceFill(['status' => TripStatus::Draft])->save());
        $this->assertNull($this->pageOf($released)['trip']['url']);
    }

    public function test_ac_32_other_statuses_show_their_glossary_pill(): void
    {
        $review = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::NeedsReview);
        $paid = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);

        $this->assertSame('needs_review', $this->pageOf($review)['booking']['status']);
        $props = $this->pageOf($paid);
        $this->assertSame('paid', $props['booking']['status']);
        $this->assertSame(
            ['awaiting_payment' => 'Menunggu pembayaran', 'needs_review' => 'Perlu dicek', 'paid' => 'Lunas', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'completed' => 'Selesai'],
            $props['copy']['status'],
        );
    }

    public function test_ac_33_kabari_operator_links_to_the_seller_whatsapp_with_the_booking(): void
    {
        $booking = $this->book(['participants' => 2]);

        $text = 'Halo Kencana Trip, saya sudah pesan Karimunjawa 3H2M tanggal 24–26 Okt untuk 2 orang. Kode pesanan RKB-KNC-1024-0001.';
        $this->assertSame('https://wa.me/6281277881020?text='.rawurlencode($text), $this->pageOf($booking)['organization']['whatsapp_url']);

        $template = file_get_contents(resource_path('js/pages/Public/Booking.vue'));
        $this->assertStringContainsString('rel="noopener noreferrer"', $template);
        $this->assertStringContainsString('target="_blank"', $template);
        $this->assertStringNotContainsString('bg-prada', $template, 'No gold button until F-006.');
    }

    public function test_ac_34_the_page_outlives_the_trip_and_departure_state(): void
    {
        $booking = $this->book();

        $this->asTenant($this->a, fn () => $this->departure->forceFill(['status' => 'closed', 'closed_reason' => 'manual'])->save());
        $this->asTenant($this->a, fn () => $this->trip->forceFill(['status' => TripStatus::Draft])->save());
        $this->travelTo(CarbonImmutable::parse('2024-10-28 09:00', 'Asia/Jakarta'));

        $this->get('/kencana/pesanan/'.$booking->access_token)->assertOk();
    }

    public function test_ac_35_wrong_changed_or_foreign_tokens_and_references_are_the_same_404(): void
    {
        $booking = $this->book();
        $b = $this->seller('bahari', 'Bahari');
        $foreign = $this->bookingFor($b, $this->departureFor($b, $this->bookableTrip($b), '2024-10-24'), 1);
        $token = $booking->access_token;
        $changed = substr($token, 0, -1).($token[31] === 'a' ? 'b' : 'a');

        foreach ([Str::random(32), $foreign->access_token, $changed, $booking->reference, substr($token, 0, 31), strtolower($token) === $token ? strtoupper($token) : strtolower($token)] as $candidate) {
            $this->get('/kencana/pesanan/'.$candidate)->assertNotFound()->assertSee('Halaman tidak ditemukan');
        }
    }

    public function test_ac_36_headers_and_head_tags(): void
    {
        $booking = $this->book();

        $response = $this->get('/kencana/pesanan/'.$booking->access_token)->assertOk();

        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));

        $head = Str::before($response->getContent(), '</head>');
        $this->assertStringContainsString('<title>Pesanan Anda · Kencana Trip</title>', $head);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $head);
        $this->assertStringNotContainsString('og:', $head);
        $this->assertStringNotContainsString('Rina', $head);
        $this->assertStringNotContainsString('RKB-', $head);
    }

    public function test_ac_37_the_page_props_are_exactly_the_public_fields(): void
    {
        $booking = $this->book();
        $this->bookingFor($this->a, $this->departure, 1, attributes: ['customer_name' => 'Pemesan Lain']);

        $props = $this->pageOf($booking);

        $this->assertEqualsCanonicalizing(
            ['title', 'organization', 'booking', 'trip', 'departure', 'meeting', 'context', 'copy', 'calendar', 'urls', 'errors'],
            array_keys($props),
        );
        $this->assertSame(['name', 'slug', 'whatsapp_url'], array_keys($props['organization']));
        $this->assertSame(['reference', 'status', 'cancel_reason', 'participants', 'unit_price', 'total_amount', 'customer_name', 'customer_phone', 'hold_expires_at'], array_keys($props['booking']));
        $this->assertSame(['name', 'url'], array_keys($props['trip']));
        $this->assertSame(['start_date', 'end_date'], array_keys($props['departure']));
        $this->assertSame(['now', 'timezone'], array_keys($props['context']));
        $this->assertSame(['home'], array_keys($props['urls']));

        $json = json_encode($props);
        foreach (['"id"', 'organization_id', 'access_token', 'submission_key', 'seats_taken', 'Pemesan Lain', $booking->access_token, (string) $booking->public_id] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }
}
