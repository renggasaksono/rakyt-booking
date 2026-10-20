<?php

namespace Tests\Feature\Payments;

use App\Audit\AuditAction;
use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 section B: the customer uploads payment evidence. The clock is
 * Thursday 2024-10-10 10:00 WIB; the hold is 120 minutes.
 */
class UploadEvidenceTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        Storage::fake(config('rakyt.media.private_disk'));
        $this->a = $this->seller();
        $trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $trip, '2024-10-24');
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create());
    }

    private function awaiting(): Booking
    {
        return $this->bookingFor($this->a, $this->departure, 2, BookingStatus::AwaitingPayment, [
            'created_at' => CarbonImmutable::now(),
            'hold_expires_at' => CarbonImmutable::now()->addMinutes(120),
        ]);
    }

    private function upload(Booking $booking, ?UploadedFile $file = null): TestResponse
    {
        return $this->post(
            route('public.booking.evidence', ['organization' => 'kencana', 'token' => $booking->access_token]),
            ['bukti' => $file ?? UploadedFile::fake()->image('bukti.jpg')],
        );
    }

    public function test_ac_12_upload_moves_the_booking_to_needs_review_and_records_evidence(): void
    {
        $booking = $this->awaiting();

        $this->upload($booking)->assertStatus(303);

        $booking->refresh();
        $this->assertSame(BookingStatus::NeedsReview, $booking->status);
        // The hold timestamp is left as-is but no longer counts (ADR-0006).
        $this->assertEquals(CarbonImmutable::now()->addMinutes(120)->timestamp, $booking->hold_expires_at->timestamp);

        $record = PaymentRecord::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(2_500_000, $record->amount_due);
        $this->assertNotNull($record->evidence_path);
        $this->assertNotNull($record->evidence_uploaded_at);
        Storage::disk(config('rakyt.media.private_disk'))->assertExists($record->evidence_path);

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::PaymentEvidenceUploaded->value,
            'actor_type' => 'customer',
            'subject_id' => (string) $booking->id,
        ]);
    }

    public function test_ac_12_evidence_path_never_enters_the_audit_event(): void
    {
        $booking = $this->awaiting();
        $this->upload($booking)->assertStatus(303);

        $record = PaymentRecord::query()->where('booking_id', $booking->id)->firstOrFail();
        $after = AuditEvent::query()->where('action', AuditAction::PaymentEvidenceUploaded->value)->value('after');

        $this->assertStringNotContainsString($record->evidence_path, json_encode($after));
        $this->assertSame(['status' => 'needs_review'], $after);
    }

    public function test_ac_13_upload_is_refused_at_the_two_hour_boundary(): void
    {
        $booking = $this->awaiting();

        // One second before expiry: accepted.
        $this->travelTo(CarbonImmutable::now()->addMinutes(120)->subSecond());
        $this->upload($booking)->assertStatus(303);
        $this->assertSame(BookingStatus::NeedsReview, $booking->refresh()->status);

        // A fresh booking at exactly the deadline: refused.
        $other = $this->awaiting();
        $this->travelTo(CarbonImmutable::parse($other->hold_expires_at));
        $this->upload($other)->assertStatus(303);
        $this->assertSame(BookingStatus::AwaitingPayment, $other->refresh()->status);
        $this->assertDatabaseMissing('payment_records', ['booking_id' => $other->id]);
    }

    public function test_ac_14_a_non_image_or_oversize_file_is_refused(): void
    {
        $booking = $this->awaiting();

        $this->upload($booking, UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->assertSessionHasErrors('bukti');
        $this->upload($booking, UploadedFile::fake()->create('big.jpg', 6 * 1024, 'image/jpeg'))
            ->assertSessionHasErrors('bukti');

        $this->assertSame(BookingStatus::AwaitingPayment, $booking->refresh()->status);
        $this->assertDatabaseMissing('payment_records', ['booking_id' => $booking->id]);
    }

    public function test_ac_14_a_missing_file_is_refused(): void
    {
        $booking = $this->awaiting();

        $this->post(route('public.booking.evidence', ['organization' => 'kencana', 'token' => $booking->access_token]), [])
            ->assertSessionHasErrors('bukti');

        $this->assertSame(BookingStatus::AwaitingPayment, $booking->refresh()->status);
    }

    public function test_ac_14_upload_is_rate_limited_per_token(): void
    {
        $booking = $this->awaiting();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('public.booking.evidence', ['organization' => 'kencana', 'token' => $booking->access_token]), []);
        }

        $this->post(route('public.booking.evidence', ['organization' => 'kencana', 'token' => $booking->access_token]), [])
            ->assertStatus(429);
    }

    public function test_ac_8_and_15_a_needs_review_booking_replaces_its_evidence(): void
    {
        $booking = $this->awaiting();
        $this->upload($booking)->assertStatus(303);
        $first = PaymentRecord::query()->where('booking_id', $booking->id)->firstOrFail()->evidence_path;

        $this->upload($booking)->assertStatus(303);

        $record = PaymentRecord::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(BookingStatus::NeedsReview, $booking->refresh()->status);
        $this->assertNotSame($first, $record->evidence_path);
        // The replaced file is removed; only one record exists.
        Storage::disk(config('rakyt.media.private_disk'))->assertMissing($first);
        $this->assertDatabaseCount('payment_records', 1);
    }

    public function test_ac_15_upload_is_refused_against_paid_rejected_or_cancelled(): void
    {
        foreach ([BookingStatus::Paid, BookingStatus::Rejected, BookingStatus::Cancelled] as $status) {
            $attributes = $status === BookingStatus::Cancelled ? ['cancel_reason' => 'hold_expired'] : [];
            $booking = $this->bookingFor($this->a, $this->departure, 1, $status, $attributes);

            $this->upload($booking)->assertStatus(303);

            $this->assertSame($status, $booking->refresh()->status);
            $this->assertDatabaseMissing('payment_records', ['booking_id' => $booking->id]);
        }
    }
}
