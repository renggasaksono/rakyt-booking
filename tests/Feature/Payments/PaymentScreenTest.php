<?php

namespace Tests\Feature\Payments;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 section A: the customer payment screen on /{slug}/pesanan/{token}.
 * The clock is Thursday 2024-10-10 10:00 WIB.
 */
class PaymentScreenTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        Storage::fake(config('rakyt.media.private_disk'));
        $this->a = $this->seller();
        $this->trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    private function bank(array $attributes = []): PaymentMethod
    {
        return $this->asTenant($this->a, fn () => PaymentMethod::factory()->create($attributes));
    }

    private function qris(): PaymentMethod
    {
        return $this->asTenant($this->a, fn () => PaymentMethod::factory()->create([
            'type' => 'qris',
            'provider' => 'qris',
            'account_number' => null,
            'account_holder' => null,
            'qris_image_path' => 'org/'.$this->a->organization->public_id.'/payments/qris/img.png',
            'position' => 1,
        ]));
    }

    private function bookingOf(BookingStatus $status = BookingStatus::AwaitingPayment, array $attributes = []): Booking
    {
        return $this->bookingFor($this->a, $this->departure, 2, $status, $attributes);
    }

    private function pageOf(Booking $booking): array
    {
        return $this->bookingPageProps('kencana', $booking->access_token);
    }

    public function test_ac_1_instructions_list_active_methods_with_the_amount(): void
    {
        $this->bank(['provider' => 'bca', 'account_number' => '1234567890', 'account_holder' => 'Agus Santoso', 'position' => 0]);
        $booking = $this->bookingOf();

        $props = $this->pageOf($booking);

        $this->assertSame(2_500_000, $props['amount_due']);
        $this->assertCount(1, $props['payment']['methods']);
        $this->assertSame([
            'type' => 'bank',
            'provider_label' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Agus Santoso',
            'qris_image_url' => null,
        ], $props['payment']['methods'][0]);
    }

    public function test_ac_1_methods_are_listed_in_position_order(): void
    {
        $this->bank(['provider' => 'bri', 'account_number' => '2222222222', 'position' => 1]);
        $this->bank(['provider' => 'bca', 'account_number' => '1111111111', 'position' => 0]);
        $booking = $this->bookingOf();

        $labels = array_column($this->pageOf($booking)['payment']['methods'], 'provider_label');

        $this->assertSame(['BCA', 'BRI'], $labels);
    }

    public function test_ac_2_account_holder_hint_copy_is_present(): void
    {
        $this->bank();
        $props = $this->pageOf($this->bookingOf());

        $this->assertSame('Harus sama persis dengan buku tabungan.', $props['copy']['payment']['account_holder_hint']);
    }

    public function test_ac_3_copy_toasts_are_present(): void
    {
        $this->bank();
        $props = $this->pageOf($this->bookingOf());

        $this->assertSame('Nomor disalin', $props['copy']['payment']['copied_number']);
        $this->assertSame('Jumlah disalin', $props['copy']['payment']['copied_amount']);
    }

    public function test_ac_4_progress_tracks_the_status(): void
    {
        $this->bank();

        $this->assertSame(1, $this->pageOf($this->bookingOf(BookingStatus::AwaitingPayment))['progress']);
        $this->assertSame(2, $this->pageOf($this->bookingOf(BookingStatus::NeedsReview))['progress']);
        $this->assertSame(3, $this->pageOf($this->bookingOf(BookingStatus::Paid))['progress']);
        $this->assertNull($this->pageOf($this->bookingOf(BookingStatus::Rejected))['progress']);
        $this->assertNull($this->pageOf($this->bookingOf(BookingStatus::Cancelled, ['cancel_reason' => 'hold_expired']))['progress']);
    }

    public function test_ac_5_and_6_cta_and_no_cut_copy_present(): void
    {
        $this->bank();
        $props = $this->pageOf($this->bookingOf());

        $this->assertSame('Unggah bukti transfer', $props['copy']['payment']['upload_cta']);
        $this->assertSame('Pembayaran langsung ke :seller. Rakyt tidak memotong biaya transaksi.', $props['copy']['payment']['direct_notice']);
    }

    public function test_ac_7_no_active_method_shows_the_contact_notice_and_hides_methods(): void
    {
        PaymentMethod::query()->delete();
        $props = $this->pageOf($this->bookingOf());

        $this->assertSame([], $props['payment']['methods']);
        $this->assertSame('Operator belum memasang instruksi pembayaran. Hubungi via WhatsApp.', $props['copy']['payment']['no_methods']);
    }

    public function test_ac_8_needs_review_shows_the_uploaded_evidence(): void
    {
        $this->bank();
        $booking = $this->bookingOf(BookingStatus::NeedsReview);
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->for($booking)->withEvidence()->create([
            'evidence_uploaded_at' => CarbonImmutable::now(),
        ]));

        $props = $this->pageOf($booking);

        $this->assertSame(2, $props['progress']);
        $this->assertNotNull($props['evidence']);
        $this->assertSame(route('public.booking.evidence.show', ['organization' => 'kencana', 'token' => $booking->access_token]), $props['evidence']['thumb_url']);
        $this->assertSame('Bukti sudah dikirim. Menunggu operator mengecek.', $props['copy']['payment']['under_review']);
    }

    public function test_ac_10_rejected_shows_the_reason(): void
    {
        $this->bank();
        $booking = $this->bookingOf(BookingStatus::Rejected);
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->for($booking)->rejected('Nominal tidak sesuai')->create());

        $props = $this->pageOf($booking);

        $this->assertNull($props['progress']);
        $this->assertSame('Nominal tidak sesuai', $props['reject_reason']);
    }

    public function test_ac_22_qris_method_is_shown_as_a_gated_url_without_a_number(): void
    {
        $this->bank(['position' => 0]);
        $this->qris();
        $booking = $this->bookingOf();

        $methods = $this->pageOf($booking)['payment']['methods'];

        $this->assertSame('bank', $methods[0]['type']);
        $this->assertSame('qris', $methods[1]['type']);
        $this->assertNull($methods[1]['account_number']);
        $this->assertSame(route('public.booking.qris', ['organization' => 'kencana', 'token' => $booking->access_token]), $methods[1]['qris_image_url']);
    }

    public function test_ac_27_props_never_leak_storage_path_or_ids(): void
    {
        $this->bank();
        $booking = $this->bookingOf(BookingStatus::NeedsReview);
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->for($booking)->withEvidence('org/secret/evidence/leak.jpg')->create());

        $json = json_encode($this->pageOf($booking));

        foreach (['evidence_path', 'org/secret/evidence/leak.jpg', 'organization_id', 'seats_taken'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }
}
