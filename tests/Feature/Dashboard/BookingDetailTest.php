<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-007 section D: the booking detail `/pesanan/{public_id}`, AC-23–AC-26.
 * The clock starts at Thursday 2024-10-10 10:00 WIB.
 */
class BookingDetailTest extends TestCase
{
    use BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->trip = $this->bookableTrip($this->a, ['name' => 'Karimunjawa 3H2M', 'meeting_point' => 'Pelabuhan Jepara', 'meeting_time' => '06:00']);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    private function booking(BookingStatus $status = BookingStatus::AwaitingPayment, array $attributes = []): Booking
    {
        return $this->bookingFor($this->a, $this->departure, 2, $status, [
            'reference' => 'RKB-KCN-1024-0142',
            'customer_name' => 'Rina Wulandari',
            'customer_phone' => '+6281234567890',
            'notes' => "Vegetarian 1 orang\nJemput di hotel",
            ...$attributes,
        ]);
    }

    public function test_ac_23_the_detail_shows_customer_trip_and_payment(): void
    {
        $booking = $this->booking();

        $props = $this->detailProps($this->a, $booking);

        $this->assertSame([
            'public_id' => $booking->public_id,
            'reference' => 'RKB-KCN-1024-0142',
            'customer_name' => 'Rina Wulandari',
            'participants' => 2,
            'total_amount' => 2_500_000,
            'status' => 'awaiting_payment',
            'hold_expires_at' => '2024-10-10T05:00:00Z',
            'created_at' => '2024-10-10T03:00:00Z',
            'trip' => ['name' => 'Karimunjawa 3H2M'],
            'departure' => ['start_date' => '2024-10-24', 'end_date' => '2024-10-26'],
            'urls' => ['show' => url('/pesanan/'.$booking->public_id)],
            'customer_phone' => '+6281234567890',
            'notes' => "Vegetarian 1 orang\nJemput di hotel",
            'unit_price' => 1_250_000,
            'cancel_reason' => null,
            'customer_url' => 'https://rakyt.id/kencana/pesanan/'.$booking->access_token,
            'can_cancel' => true,
            // F-006: verification state behind the detail.
            'can_verify' => false,
            'payment' => [
                'amount_due' => 2_500_000,
                'has_evidence' => false,
                'evidence_url' => null,
                'uploaded_at' => null,
                'decision' => null,
                'reject_reason' => null,
                'verified_at' => null,
            ],
        ], $props['booking']);
        $this->assertSame(['name' => 'Karimunjawa 3H2M', 'edit_url' => url('/trip/'.$this->trip->public_id)], $props['trip']);
        $this->assertSame(['point' => 'Pelabuhan Jepara', 'time' => '06:00'], $props['meeting']);
        $this->assertSame(['now' => '2024-10-10T03:00:00Z', 'timezone' => 'Asia/Jakarta', 'today' => '2024-10-10'], $props['context']);
        $this->assertSame(url('/pesanan'), $props['urls']['back']);
        $this->assertSame(['Dibuat :date', 'Kuota ditahan s.d. :time', ':n peserta × :price'], [$props['copy']['show']['created'], $props['copy']['show']['hold_until'], $props['copy']['show']['line']]);
    }

    public function test_ac_23_amounts_come_from_the_booking_snapshot_not_the_current_price(): void
    {
        $booking = $this->booking();
        Trip::acrossOrganizations()->whereKey($this->trip->id)->update(['base_price' => 2_000_000]);
        TripDeparture::acrossOrganizations()->whereKey($this->departure->id)->update(['price_override' => 1_900_000]);

        $props = $this->detailProps($this->a, $booking)['booking'];

        $this->assertSame([1_250_000, 2_500_000], [$props['unit_price'], $props['total_amount']]);
    }

    public function test_ac_23_cancel_reasons_and_empty_notes(): void
    {
        $expired = $this->booking(BookingStatus::Cancelled, ['notes' => null]);
        $seller = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Cancelled, ['cancel_reason' => 'seller']);

        $this->assertSame(['hold_expired', null, false], [
            $this->detailProps($this->a, $expired)['booking']['cancel_reason'],
            $this->detailProps($this->a, $expired)['booking']['notes'],
            $this->detailProps($this->a, $expired)['booking']['can_cancel'],
        ]);
        $props = $this->detailProps($this->a, $seller);
        $this->assertSame('seller', $props['booking']['cancel_reason']);
        $this->assertSame([
            'hold_expired' => 'Waktu pembayaran habis, kuota dilepas otomatis.',
            'seller' => 'Dibatalkan oleh Anda.',
        ], $props['copy']['show']['cancel_reasons']);
    }

    public function test_ac_24_the_customer_link_decrypts_to_a_working_token_and_the_page_is_not_stored(): void
    {
        $booking = $this->booking();

        $response = $this->actingAsTenant($this->a)->get('/pesanan/'.$booking->public_id);
        $response->assertOk();
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));

        $url = $response->viewData('page')['props']['booking']['customer_url'];
        $token = substr($url, strrpos($url, '/') + 1);
        $this->assertSame(32, strlen($token));
        $this->assertSame($booking->access_token_hash, Booking::hashToken($token));

        // The customer page opens with it.
        $this->post(route('logout'));
        $this->get('/kencana/pesanan/'.$token)->assertOk();
    }

    public function test_ac_25_whatsapp_chat_and_reminder_urls(): void
    {
        $booking = $this->booking();

        $whatsapp = $this->detailProps($this->a, $booking)['whatsapp'];

        $this->assertSame('https://wa.me/6281234567890', $whatsapp['chat_url']);
        $text = 'Halo Rina Wulandari, pesanan RKB-KCN-1024-0142 untuk Karimunjawa 3H2M tanggal 24–26 Okt (2 orang) menunggu pembayaran Rp2.500.000. '
            .'Kuota ditahan sampai 12.00. Detail pesanan: https://rakyt.id/kencana/pesanan/'.$booking->access_token;
        $this->assertSame('https://wa.me/6281234567890?text='.rawurlencode($text), $whatsapp['reminder_url']);
        $this->assertNull($whatsapp['cancelled_url']);

        $page = file_get_contents(resource_path('js/pages/Bookings/Show.vue'));
        $this->assertMatchesRegularExpression('/:href="whatsapp\.chat_url"\s+target="_blank"\s+rel="noopener noreferrer"/', $page);
        $this->assertMatchesRegularExpression('/:href="whatsapp\.reminder_url"\s+target="_blank"\s+rel="noopener noreferrer"/', $page);
    }

    public function test_ac_25_a_release_on_the_next_local_day_says_besok(): void
    {
        $this->freezeClock('2024-10-10 23:10');
        $booking = $this->booking(attributes: ['hold_expires_at' => CarbonImmutable::now()->addHours(2)]);

        $url = $this->detailProps($this->a, $booking)['whatsapp']['reminder_url'];

        $this->assertStringContainsString(rawurlencode('Kuota ditahan sampai besok 01.10.'), $url);
    }

    public function test_ac_25_no_reminder_without_an_active_hold(): void
    {
        $expired = $this->expiredHoldFor($this->a, $this->departure, 1);
        $paid = $this->booking(BookingStatus::Paid);

        $this->assertNull($this->detailProps($this->a, $expired)['whatsapp']['reminder_url']);
        $this->assertNull($this->detailProps($this->a, $paid)['whatsapp']['reminder_url']);
        $this->assertSame('https://wa.me/6281234567890', $this->detailProps($this->a, $paid)['whatsapp']['chat_url']);
    }

    public function test_ac_25_the_hari_ini_reminder_redirects_to_whatsapp_without_exposing_the_token_in_props(): void
    {
        $booking = $this->booking();

        $response = $this->actingAsTenant($this->a)->get('/pesanan/'.$booking->public_id.'/ingatkan');

        $response->assertRedirect($this->detailProps($this->a, $booking)['whatsapp']['reminder_url']);
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        // Expired meanwhile: back to the detail with the notice.
        $this->travel(3)->hours();
        $this->actingAsTenant($this->a)->get('/pesanan/'.$booking->public_id.'/ingatkan')
            ->assertRedirect('/pesanan/'.$booking->public_id)
            ->assertSessionHas('notice', 'Pesanan ini sudah tidak aktif.');
    }

    public function test_ac_26_unknown_or_malformed_ids_the_numeric_id_reference_and_token_are_404(): void
    {
        $booking = $this->booking();

        foreach (['01jabcdefghjkmnpqrstvwxyz0', 'x', (string) $booking->id, $booking->reference, $booking->access_token, str_repeat('a', 300), '%00'] as $id) {
            $this->actingAsTenant($this->a)->get('/pesanan/'.$id)->assertNotFound();
        }
    }
}
