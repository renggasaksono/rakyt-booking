<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-008 section B: seller → customer links (reminder/cancelled/rejected/chat)
 * ride on the booking's own customer number, with the invalid-number guard
 * (AC-B5, edge case) and tenant isolation (AC-B11). Exact strings for reminder
 * (AC-B6) and cancelled (AC-B7) live in BookingDetailTest/CancelBookingTest;
 * this adds rejected (AC-B8) and the isolation/guard cases.
 */
class SellerToCustomerWhatsAppTest extends TestCase
{
    use BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        // The organization's own number differs from the customer's (AC-B11).
        $this->a = $this->seller('kencana', 'Kencana Trip', ['whatsapp' => '+6281277881020']);
        $this->trip = $this->bookableTrip($this->a, ['name' => 'Karimunjawa 3H2M']);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    private function booking(BookingStatus $status = BookingStatus::AwaitingPayment, array $attributes = []): Booking
    {
        return $this->bookingFor($this->a, $this->departure, 2, $status, [
            'reference' => 'RKB-KCN-1024-0142',
            'customer_name' => 'Rina Wulandari',
            'customer_phone' => '+6281234567890',
            ...$attributes,
        ]);
    }

    public function test_ac_b8_a_rejected_proof_offers_kabari_pelanggan_on_the_customer_number(): void
    {
        $booking = $this->booking(BookingStatus::Rejected);

        $text = 'Halo Rina Wulandari, bukti transfer untuk pesanan RKB-KCN-1024-0142 belum bisa kami terima. Silakan hubungi kami.';
        $this->assertSame(
            'https://wa.me/6281234567890?text='.rawurlencode($text),
            $this->detailProps($this->a, $booking)['whatsapp']['rejected_url'],
        );
    }

    public function test_ac_b5_an_invalid_customer_number_hides_every_seller_to_customer_link(): void
    {
        $booking = $this->booking(attributes: ['customer_phone' => '-']);

        $whatsapp = $this->detailProps($this->a, $booking)['whatsapp'];

        $this->assertNull($whatsapp['chat_url']);
        $this->assertNull($whatsapp['reminder_url']);
    }

    public function test_ac_b11_links_carry_the_bookings_own_customer_number_not_the_org_number(): void
    {
        $booking = $this->booking();

        $whatsapp = $this->detailProps($this->a, $booking)['whatsapp'];

        foreach (['chat_url', 'reminder_url'] as $key) {
            $this->assertStringContainsString('wa.me/6281234567890', $whatsapp[$key]);
            // Never the organization's own number.
            $this->assertStringNotContainsString('6281277881020', $whatsapp[$key]);
        }
    }

    public function test_ac_b11_seller_b_cannot_obtain_a_link_for_seller_as_booking(): void
    {
        $booking = $this->booking();
        $b = $this->seller('bahari', 'Bahari Tour', ['whatsapp' => '+6289999988888']);

        // Booking scope + policy block the detail: no props, so no URL.
        $this->actingAsTenant($b)->get('/pesanan/'.$booking->public_id)->assertNotFound();
        $this->actingAsTenant($b)->get('/pesanan/'.$booking->public_id.'/ingatkan')->assertNotFound();
    }
}
