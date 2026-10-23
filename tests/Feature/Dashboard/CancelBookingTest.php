<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingService;
use App\Bookings\BookingStatus;
use App\Bookings\CancelReason;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\DepartureStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-007 section E: the seller cancels a booking, AC-27–AC-30, AC-32, AC-33
 * (AC-31's two-connection race is in CancelConcurrencyTest). The seat
 * invariant is checked after every test (BuildsBookings). The clock starts at
 * Thursday 2024-10-10 10:00 WIB.
 */
class CancelBookingTest extends TestCase
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
        $this->trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24', ['capacity' => 4]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function cancel(Booking $booking, array $query = []): TestResponse
    {
        return $this->actingAsTenant($this->a)->post(route('bookings.cancel', [$booking, ...$query]));
    }

    private function fresh(Booking $booking): Booking
    {
        return Booking::acrossOrganizations()->findOrFail($booking->id);
    }

    public function test_ac_27_the_offer_is_shown_for_holding_statuses_only(): void
    {
        $holding = [
            $this->bookingFor($this->a, $this->departure, 1),
            $this->expiredHoldFor($this->a, $this->departure, 1),
            $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview),
            $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid),
        ];
        $other = [
            $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Cancelled),
            $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Rejected),
            $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Completed),
        ];

        foreach ($holding as $booking) {
            $this->assertTrue($this->detailProps($this->a, $booking)['booking']['can_cancel'], $booking->status->value);
        }

        foreach ($other as $booking) {
            $this->assertFalse($this->detailProps($this->a, $booking)['booking']['can_cancel'], $booking->status->value);
        }

        $page = file_get_contents(resource_path('js/pages/Bookings/Show.vue'));
        $this->assertMatchesRegularExpression('/v-if="booking\.can_cancel"[^>]*class="[^"]*border-2 border-status-rejected[^"]*text-status-rejected/s', $page);
    }

    public function test_ac_28_the_confirmation_copy(): void
    {
        $props = $this->detailProps($this->a, $this->bookingFor($this->a, $this->departure, 2, BookingStatus::Paid));

        $this->assertSame([
            'title' => 'Batalkan pesanan :reference?',
            'seats' => 'Kuota :n orang di keberangkatan :date akan dibuka lagi.',
            'no_notice' => 'Pelanggan tidak diberi tahu otomatis. Kabari lewat WhatsApp.',
            'paid' => 'Pesanan ini sudah lunas. Pengembalian dana diurus langsung antara Anda dan pelanggan.',
            'confirm' => 'Batalkan pesanan',
            'back' => 'Kembali',
        ], array_intersect_key($props['copy']['cancel'], array_flip(['title', 'seats', 'no_notice', 'paid', 'confirm', 'back'])));

        $page = file_get_contents(resource_path('js/pages/Bookings/Show.vue'));
        $this->assertStringContainsString("v-if=\"booking.status === 'paid'\"", $page);
        $this->assertSame(0, substr_count($page, 'bg-prada'));
        $this->assertStringContainsString('bg-status-rejected', $page);
    }

    public function test_ac_29_cancelling_returns_the_seats_and_writes_one_audit_event(): void
    {
        foreach ([BookingStatus::AwaitingPayment, BookingStatus::NeedsReview, BookingStatus::Paid] as $status) {
            $booking = $this->bookingFor($this->a, $this->departure, 2);
            if ($status !== BookingStatus::AwaitingPayment) {
                $booking = $this->setStatus($booking, $status);
            }
            $hold = $booking->hold_expires_at;

            $this->cancel($booking)
                ->assertRedirect('/pesanan/'.$booking->public_id)
                ->assertSessionHas('toast', 'Pesanan dibatalkan. Kuota 2 orang dibuka lagi.');

            $fresh = $this->fresh($booking);
            $this->assertSame([BookingStatus::Cancelled, CancelReason::Seller], [$fresh->status, $fresh->cancel_reason], $status->value);
            $this->assertTrue($hold->equalTo($fresh->hold_expires_at));
            $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);

            $event = AuditEvent::acrossOrganizations()->where('action', 'booking.cancelled')->where('subject_id', (string) $booking->id)->sole();
            $this->assertSame(['user', (string) $this->a->owner->id, (string) $booking->id], [$event->actor_type, $event->actor_id, $event->subject_id]);
            $this->assertSame($this->a->organization->id, $event->organization_id);
            $this->assertNotNull($event->ip);
            $this->assertEquals(['status' => $status->value, 'cancel_reason' => null], $event->before);
            $this->assertEquals(['status' => 'cancelled', 'cancel_reason' => 'seller'], $event->after);
        }
    }

    public function test_ac_29_the_detail_then_offers_kabari_pelanggan(): void
    {
        $booking = $this->bookingFor($this->a, $this->departure, 2, attributes: ['customer_name' => 'Rina', 'reference' => 'RKB-KCN-1024-0142']);
        $this->cancel($booking, ['status' => 'perlu_tindakan'])->assertRedirect('/pesanan/'.$booking->public_id.'?status=perlu_tindakan');

        $whatsapp = $this->detailProps($this->a, $booking)['whatsapp'];

        $this->assertSame('https://wa.me/6281234567890?text='.rawurlencode('Halo Rina, pesanan RKB-KCN-1024-0142 untuk Karimunjawa 3H2M tanggal 24–26 Okt dibatalkan. Hubungi kami jika ada pertanyaan.'), $whatsapp['cancelled_url']);
        $this->assertNull($whatsapp['reminder_url']);
    }

    public function test_ac_29_cancel_goes_through_the_booking_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Bookings/SellerBookingController.php'));

        $this->assertStringContainsString('->cancelBySeller(', $controller);
        $this->assertStringNotContainsString('seats_taken', $controller);
    }

    public function test_ac_30_an_already_released_booking_changes_nothing(): void
    {
        $booking = $this->expiredHoldFor($this->a, $this->departure, 2);
        app(ReleaseExpiredHolds::class)();

        $this->cancel($booking)
            ->assertRedirect('/pesanan/'.$booking->public_id)
            ->assertSessionHas('notice', 'Pesanan ini sudah tidak aktif.');

        $this->assertSame(CancelReason::HoldExpired, $this->fresh($booking)->cancel_reason);
        $this->assertSame(0, AuditEvent::acrossOrganizations()->where('action', 'booking.cancelled')->count());
        $this->actingAsTenant($this->a)->get('/pesanan/'.$booking->public_id)->assertOk();
    }

    public function test_ac_30_posting_twice_gives_the_same_end_state(): void
    {
        $booking = $this->bookingFor($this->a, $this->departure, 2);
        $this->bookingFor($this->a, $this->departure, 1);

        $this->cancel($booking)->assertSessionHas('toast');
        $this->cancel($booking)->assertSessionHas('notice', 'Pesanan ini sudah tidak aktif.');

        $this->assertSame(1, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.cancelled')->count());
        // The service itself says whether it changed anything.
        $this->assertFalse($this->asTenant($this->a, fn () => app(BookingService::class)->cancelBySeller($this->fresh($booking), $this->a->owner)));
    }

    public function test_ac_32_seats_go_back_on_sale_and_a_closed_departure_stays_closed(): void
    {
        $this->bookingFor($this->a, $this->departure, 2, BookingStatus::Paid);
        $booking = $this->bookingFor($this->a, $this->departure, 2);
        $this->assertTrue($this->freshDeparture($this->departure)->isFull());

        $this->cancel($booking);

        $this->assertSame(2, $this->todayProps($this->a)['departures'][0]['seats']['empty']);
        $this->post(route('logout'));
        $trip = collect($this->tripProps('kencana', 'karimunjawa-3h2m')['departures'])->firstWhere('public_id', $this->departure->public_id);
        $this->assertSame(2, $trip['seats_left']);
        $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($this->departure, ['participants' => 2]))->assertStatus(303);
        $this->assertSame(4, $this->freshDeparture($this->departure)->seats_taken);

        // Closed by the seller: cancelling never reopens it.
        $closed = $this->departureFor($this->a, $this->trip, '2024-10-31', ['status' => DepartureStatus::Closed, 'closed_reason' => 'manual']);
        $onClosed = $this->bookingFor($this->a, $closed, 1, BookingStatus::Paid);
        $this->cancel($onClosed);
        $this->assertSame([DepartureStatus::Closed, 0], [$this->freshDeparture($closed)->status, $this->freshDeparture($closed)->seats_taken]);
    }

    public function test_ac_33_bookings_on_past_departures_can_be_cancelled(): void
    {
        $past = $this->departureFor($this->a, $this->trip, '2024-10-01');
        $booking = $this->bookingFor($this->a, $past, 3, BookingStatus::Paid);

        $this->cancel($booking)->assertSessionHas('toast', 'Pesanan dibatalkan. Kuota 3 orang dibuka lagi.');

        $this->assertSame(BookingStatus::Cancelled, $this->fresh($booking)->status);
        $this->assertSame(0, $this->freshDeparture($past)->seats_taken);
    }
}
