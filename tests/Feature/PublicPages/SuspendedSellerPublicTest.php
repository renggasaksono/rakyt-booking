<?php

namespace Tests\Feature\PublicPages;

use App\Bookings\BookingService;
use App\Bookings\BookingStatus;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Http\Controllers\PublicPages\BookingController;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\PublicPages\PublicVisibility;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-014 AC-3, AC-4, AC-6, AC-8: what customers meet while a seller is
 * suspended. The seller's link shows a neutral "tidak tersedia" page (200,
 * not 404) with nothing of the seller; new bookings and date requests are
 * refused; a customer's existing booking stays readable but takes no payment
 * evidence; unpaid holds still expire. Reactivation restores everything.
 * The clock is Thursday 2024-10-10 10:00 WIB.
 */
class SuspendedSellerPublicTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    private Tenant $a;

    private Trip $trip;

    private Trip $requestDate;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        Storage::fake(config('rakyt.media.private_disk'));
        $this->a = $this->seller('kencana', 'Kencana Trip', [
            'city' => 'Jepara',
            'bio' => 'Open trip Karimunjawa sejak 2015.',
            'whatsapp' => '+6281277881020',
        ]);
        $this->trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
        $this->requestDate = $this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private Komodo']));
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create(['account_number' => '1234567890', 'account_holder' => 'Agus Santoso']));
    }

    public function test_ac_4_the_seller_and_trip_pages_are_a_neutral_unavailable_state_not_a_404(): void
    {
        $this->suspendOrganization($this->a);

        foreach (['/kencana', '/kencana/karimunjawa-3h2m', '/kencana/'.$this->requestDate->slug, '/kencana/trip-yang-tidak-ada'] as $url) {
            $response = $this->get($url)
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-cache, private')
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Public/Unavailable')
                    ->where('copy.heading', 'Halaman ini sedang tidak tersedia')
                    ->where('urls.home', route('home'))
                );

            $this->assertNothingOfTheSeller($response);
            // No link preview with the seller's name; never indexed.
            $response->assertSee('<meta name="robots" content="noindex">', false)
                ->assertDontSee('og:title', false);
        }

        // PublicVisibility is the one seam (F-002 Q11).
        $this->assertFalse(app(PublicVisibility::class)->organizationIsPublic($this->a->organization->refresh()));
    }

    public function test_ac_4_the_unavailable_page_props_are_fixed_copy_only(): void
    {
        $this->suspendOrganization($this->a);

        $props = $this->propsOf($this->get('/kencana'), 'Public/Unavailable');

        $this->assertEqualsCanonicalizing(['title', 'copy', 'urls'], array_keys($props));
        $this->assertSame(['home'], array_keys($props['urls']));
        $this->assertSame('Halaman tidak tersedia · Rakyt Booking', $props['title']);

        // One page component, no gold CTA, no seller fields.
        $vue = file_get_contents(resource_path('js/pages/Public/Unavailable.vue'));
        $this->assertStringNotContainsString('bg-prada', $vue);
        $this->assertStringNotContainsString('organization', $vue);
    }

    public function test_ac_4_an_unknown_slug_is_still_a_404(): void
    {
        $this->suspendOrganization($this->a);

        $this->get('/tidak-ada')->assertNotFound();
    }

    public function test_ac_3_the_booking_form_and_its_submit_are_refused(): void
    {
        $this->suspendOrganization($this->a);

        $form = $this->get($this->formUrl('kencana', 'karimunjawa-3h2m', $this->departure->public_id))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Unavailable'));
        $this->assertNothingOfTheSeller($form);

        $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($this->departure))
            ->assertStatus(303)
            ->assertRedirect('/kencana');

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(0, AuditEvent::acrossOrganizations()->where('action', 'booking.created')->count());
        $this->assertSame(0, $this->a->organization->refresh()->last_booking_number);
    }

    public function test_ac_3_the_booking_service_refuses_under_the_organization_lock(): void
    {
        // A submit that passed the page check just before the admin suspended:
        // BookingService re-reads the organization under its lock and refuses.
        $this->suspendOrganization($this->a);
        app(CurrentOrganization::class)->set($this->a->organization);

        try {
            app(BookingService::class)->create($this->departure, [
                'name' => 'Rina Wulandari',
                'phone' => '+6281234567890',
                'participants' => 2,
                'notes' => null,
                'submission_key' => (string) Str::uuid(),
                'price' => 1_250_000,
            ]);
            $this->fail('A suspended seller must not get a booking.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('departure', $e->errors());
        }

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(0, $this->a->organization->refresh()->last_booking_number);
    }

    public function test_ac_3_no_date_request_handoff_is_offered(): void
    {
        // Before: the request-date trip hands the date request off to WhatsApp (F-004).
        $this->assertNotNull($this->tripProps('kencana', $this->requestDate->slug)['organization']['whatsapp_url']);

        $this->suspendOrganization($this->a);

        // Date requests have no endpoint of their own: the only way in is this page's handoff.
        foreach (['/kencana/'.$this->requestDate->slug, '/kencana'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Unavailable'))
                ->assertDontSee('wa.me', false)
                ->assertDontSee('6281277881020', false);
        }
    }

    public function test_ac_6_a_customer_keeps_their_booking_page_but_cannot_upload_evidence(): void
    {
        $booking = $this->awaiting();
        $this->suspendOrganization($this->a);

        // The booking record stays valid and readable (AC-7), the upload is paused.
        $props = $this->bookingPageProps('kencana', $booking->access_token);
        $this->assertSame($booking->reference, $props['booking']['reference']);
        $this->assertTrue($props['payment']['paused']);

        $this->upload($booking)
            ->assertStatus(303)
            ->assertRedirect(BookingController::bookingUrl('kencana', $booking))
            ->assertSessionHas('notice', 'Bukti bayar belum bisa diunggah: penjual ini tidak tersedia di Rakyt untuk sementara.');

        $booking->refresh();
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->status);
        $this->assertNull(PaymentRecord::acrossOrganizations()->where('booking_id', $booking->id)->first());
        $this->assertSame([], Storage::disk(config('rakyt.media.private_disk'))->allFiles());
        $this->assertSame(0, AuditEvent::acrossOrganizations()->where('action', 'payment.evidence_uploaded')->count());
    }

    public function test_ac_6_a_needs_review_booking_cannot_replace_its_evidence(): void
    {
        $booking = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::NeedsReview);
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->withEvidence('org/a/evidence/lama.jpg')->create(['booking_id' => $booking->id]));
        $this->suspendOrganization($this->a);

        $this->upload($booking)->assertSessionHas('notice');

        $this->assertSame('org/a/evidence/lama.jpg', PaymentRecord::acrossOrganizations()->where('booking_id', $booking->id)->value('evidence_path'));
    }

    public function test_ac_6_the_upload_is_refused_under_the_lock_too(): void
    {
        // The seller was suspended after the page check: BookingService re-reads the
        // organization under a shared lock and refuses; the written file is removed.
        $booking = $this->awaiting();
        $this->suspendOrganization($this->a);
        app(CurrentOrganization::class)->set($this->a->organization);

        $record = app(BookingService::class)->uploadEvidence($booking, UploadedFile::fake()->image('bukti.jpg'));

        $this->assertNull($record);
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->refresh()->status);
        $this->assertSame([], Storage::disk(config('rakyt.media.private_disk'))->allFiles());
    }

    public function test_ac_6_a_customer_still_opens_their_own_evidence_while_the_seller_is_suspended(): void
    {
        $booking = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::NeedsReview);
        $path = 'org/'.$this->a->organization->public_id.'/evidence/'.$booking->public_id.'/bukti.jpg';
        Storage::disk(config('rakyt.media.private_disk'))->put($path, 'jpg');
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->withEvidence($path)->create(['booking_id' => $booking->id]));
        $this->suspendOrganization($this->a);

        $this->get(route('public.booking.evidence.show', ['organization' => 'kencana', 'token' => $booking->access_token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_ac_6_unpaid_holds_still_expire_and_free_their_seats(): void
    {
        $expired = $this->expiredHoldFor($this->a, $this->departure, 3);
        $live = $this->awaiting();
        $this->suspendOrganization($this->a);

        $released = app(ReleaseExpiredHolds::class)();

        $this->assertSame(1, $released);
        $this->assertSame(BookingStatus::Cancelled, $expired->refresh()->status);
        $this->assertSame('hold_expired', $expired->cancel_reason->value);
        $this->assertSame(BookingStatus::AwaitingPayment, $live->refresh()->status);
        $this->assertSame(2, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->count());
    }

    public function test_q1_a_suspended_sellers_payment_instructions_never_reach_the_booking_page(): void
    {
        $path = 'org/'.$this->a->organization->public_id.'/payments/qris/img.png';
        Storage::disk(config('rakyt.media.private_disk'))->put($path, 'png');
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create([
            'type' => 'qris',
            'provider' => 'qris',
            'account_number' => null,
            'account_holder' => null,
            'qris_image_path' => $path,
            'position' => 1,
        ]));
        $booking = $this->awaiting();
        $qris = route('public.booking.qris', ['organization' => 'kencana', 'token' => $booking->access_token]);
        $this->get($qris)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->suspendOrganization($this->a);

        // No account number, holder or QRIS on the page; the booking itself stays readable.
        $this->get(BookingController::bookingUrl('kencana', $booking))
            ->assertOk()
            // (The customer's own phone, +6281234567890, contains the account number's digits.)
            ->assertDontSee('"account_number":', false)
            ->assertDontSee('Agus Santoso', false)
            ->assertDontSee('/qris', false)
            ->assertDontSee('wa.me', false)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('booking.reference', $booking->reference)
                ->where('payment', ['methods' => [], 'paused' => true])
                // Nothing that leads to a payment or a contact: no WhatsApp, progress or "Pesan lagi".
                ->where('organization.whatsapp_url', null)
                ->where('progress', null)
                ->where('trip.url', null));

        // The QRIS image route is no longer part of the booking record.
        $this->get($qris)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Unavailable'));

        $this->reactivateOrganization($this->a);

        $restored = $this->bookingPageProps('kencana', $booking->access_token);
        $this->assertCount(2, $restored['payment']['methods']);
        $this->assertNotNull($restored['organization']['whatsapp_url']);
        $this->assertSame(1, $restored['progress']);
        $this->assertNotNull($restored['trip']['url']);
        $this->get($qris)->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_ac_8_reactivation_restores_the_page_bookings_and_uploads(): void
    {
        $booking = $this->awaiting();
        $this->suspendOrganization($this->a);
        $this->get('/kencana')->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Unavailable'));

        $this->reactivateOrganization($this->a);

        $this->assertSame('Kencana Trip', $this->sellerProps()['organization']['name']);
        $this->tripProps('kencana', 'karimunjawa-3h2m');
        $this->formProps('kencana', 'karimunjawa-3h2m', $this->departure->public_id);
        $this->assertFalse($this->bookingPageProps('kencana', $booking->access_token)['payment']['paused']);

        $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($this->departure))->assertStatus(303);
        $this->assertSame(2, $this->bookingCount());

        $this->upload($booking)->assertSessionMissing('notice');
        $this->assertSame(BookingStatus::NeedsReview, $booking->refresh()->status);
    }

    private function awaiting(): Booking
    {
        return $this->bookingFor($this->a, $this->departure, 2, BookingStatus::AwaitingPayment, [
            'created_at' => CarbonImmutable::now(),
            'hold_expires_at' => CarbonImmutable::now()->addMinutes(120),
        ]);
    }

    private function upload(Booking $booking): TestResponse
    {
        return $this->post(
            route('public.booking.evidence', ['organization' => 'kencana', 'token' => $booking->access_token]),
            ['bukti' => UploadedFile::fake()->image('bukti.jpg')],
        );
    }

    /**
     * No trips, no booking CTA, no profile data (AC-4).
     */
    private function assertNothingOfTheSeller(TestResponse $response): void
    {
        foreach (['Kencana Trip', 'Jepara', 'Open trip Karimunjawa', 'Karimunjawa 3H2M', 'Private Komodo', '6281277881020', 'wa.me', 'Agus Santoso', '1234567890', 'Rp', 'og:'] as $text) {
            $response->assertDontSee($text, false);
        }

        // The only links on the page go to Rakyt's home page: no booking CTA.
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('urls', ['home' => route('home')]));
    }
}
