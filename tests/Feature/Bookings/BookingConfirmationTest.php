<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-016 — Booking confirmation: "Salin tautan pesanan" (copy link) and
 * "Simpan bukti pesanan" (browser-native print proof). A thin delta over the
 * F-005/F-006 booking page; the confirmation content, token retrieval and the
 * WhatsApp CTA are regressions covered by BookingPageTest. The clock is frozen
 * at Thursday 2024-10-10 10:00 WIB.
 */
class BookingConfirmationTest extends TestCase
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

    private function page(): string
    {
        return file_get_contents(resource_path('js/pages/Public/Booking.vue'));
    }

    public function test_ac_7_the_page_exposes_its_own_private_url_as_urls_self(): void
    {
        $booking = $this->book();

        $props = $this->bookingPageProps('kencana', $booking->access_token);

        $this->assertSame(url('/kencana/pesanan/'.$booking->access_token), $props['urls']['self']);
        // The token is carried, but never its hash, numeric id or a seller-private field.
        $this->assertStringContainsString($booking->access_token, $props['urls']['self']);
        $this->assertStringNotContainsString($booking->access_token_hash, json_encode($props));
    }

    public function test_ac_3_to_6_the_copy_link_button_hint_and_feedback_are_wired(): void
    {
        $copy = $this->bookingPageProps('kencana', $this->book()->access_token)['copy']['page'];

        // AC-3/AC-4/AC-5/AC-6: all four strings come from lang/id/booking.php.
        $this->assertSame('Salin tautan pesanan', $copy['copy_link']);
        $this->assertSame('Tautan disalin', $copy['link_copied']);
        $this->assertSame('Gagal menyalin. Salin tautan dari bilah alamat.', $copy['link_copy_failed']);
        $this->assertSame('Simpan tautan ini untuk membuka pesanan Anda lagi — tidak perlu akun.', $copy['link_hint']);

        $vue = $this->page();
        // AC-3: the button copies urls.self through the bookingProof helper (which
        // reuses the shared copyLink). The 'ok'/'failed' mapping itself is covered
        // behaviourally in tests/js/bookingProof.test.js.
        $this->assertStringContainsString("import { bookingLinkFeedback, openPrintDialog } from '../../lib/bookingProof.js'", $vue);
        $this->assertStringContainsString('bookingLinkFeedback(props.urls.self)', $vue);
        $this->assertStringContainsString('@click="copyBookingLink"', $vue);
        // AC-4/AC-5: success + failure feedback, announced politely.
        $this->assertStringContainsString('page.link_copied', $vue);
        $this->assertStringContainsString('page.link_copy_failed', $vue);
        $this->assertStringContainsString('aria-live="polite"', $vue);
        // AC-6: the one-line hint is rendered.
        $this->assertStringContainsString('page.link_hint', $vue);
    }

    public function test_ac_8_the_save_proof_button_calls_window_print(): void
    {
        $copy = $this->bookingPageProps('kencana', $this->book()->access_token)['copy']['page'];
        $this->assertSame('Simpan bukti pesanan', $copy['save_proof']);

        $vue = $this->page();
        $this->assertStringContainsString('page.save_proof', $vue);
        // openPrintDialog() wraps window.print(); tests/js/bookingProof.test.js
        // asserts it is actually invoked.
        $this->assertStringContainsString('openPrintDialog()', $vue);
        $this->assertStringContainsString('@click="printProof"', $vue);
    }

    public function test_ac_9_and_10_only_the_receipt_snapshot_prints(): void
    {
        $this->book();

        $vue = $this->page();

        // AC-10: the live page is hidden in print; a dedicated block prints instead.
        $this->assertStringContainsString('print:hidden', $vue);
        $this->assertStringContainsString('hidden px-6 py-8 text-ink print:block', $vue);

        // AC-9: the receipt carries reference, status, trip, dates, participant line
        // and total — the summary fields as loaded. The print block is the tail of
        // the template, after the (print:hidden) main closes.
        $receipt = substr($vue, strpos($vue, 'print:block'));
        foreach (['page.reference', 'booking.reference', 'status.${status}', 'trip.name', 'dates', 'page.line', 'page.total'] as $field) {
            $this->assertStringContainsString($field, $receipt);
        }
    }

    public function test_ac_2_exactly_one_gold_button_remains(): void
    {
        $this->book();

        // The new copy/print/WhatsApp actions are ink-outline; the only gold button
        // stays F-006's "Unggah bukti transfer" (AC-2, ADR-0008).
        $this->assertSame(1, substr_count($this->page(), ' bg-prada '));
    }

    public function test_ac_12_the_whatsapp_cta_is_a_plain_navigation_with_no_handler(): void
    {
        $booking = $this->book();

        // AC-12 regression: the CTA opens wa.me in a new tab and changes nothing
        // server-side. It is a bare anchor (no @click, no form) carrying the prefilled
        // link built by the presenter — so tapping it fires no request and no mutation.
        $url = $this->bookingPageProps('kencana', $booking->access_token)['organization']['whatsapp_url'];
        $this->assertStringStartsWith('https://wa.me/', $url);

        $vue = $this->page();
        $whatsapp = substr($vue, strpos($vue, 'organization.whatsapp_url'), 400);
        $this->assertStringContainsString('rel="noopener noreferrer"', $whatsapp);
        $this->assertStringContainsString('target="_blank"', $whatsapp);
        $this->assertStringNotContainsString('@click', $whatsapp);
    }
}
