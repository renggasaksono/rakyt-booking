<?php

namespace Tests\Feature\Payments;

use App\Audit\AuditAction;
use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 section C: the seller confirms or rejects a payment inline, and the
 * evidence is served only after an authorisation check. Clock 2024-10-10 10:00 WIB.
 */
class VerifyPaymentTest extends TestCase
{
    use BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

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
    }

    private function needsReview(?Tenant $tenant = null, ?TripDeparture $departure = null, int $participants = 2): Booking
    {
        $tenant ??= $this->a;
        $departure ??= $this->departure;
        $booking = $this->bookingFor($tenant, $departure, $participants, BookingStatus::NeedsReview);

        $path = 'org/'.$tenant->organization->public_id.'/evidence/'.$booking->public_id.'/bukti.jpg';
        Storage::disk(config('rakyt.media.private_disk'))->put($path, 'bukti bytes');
        $this->asTenant($tenant, fn () => PaymentRecord::factory()->for($booking)->withEvidence($path)->create());

        return $booking;
    }

    public function test_ac_17_confirm_marks_the_booking_paid_without_moving_seats(): void
    {
        $booking = $this->needsReview();
        $seatsBefore = $this->departure->refresh()->seats_taken;

        $this->actingAsTenant($this->a)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('toast', 'Pembayaran dikonfirmasi.');

        $this->assertSame(BookingStatus::Paid, $booking->refresh()->status);
        $this->assertSame($seatsBefore, $this->departure->refresh()->seats_taken);

        $record = PaymentRecord::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('confirmed', $record->decision->value);
        $this->assertSame($this->a->owner->id, $record->verified_by);
        $this->assertNotNull($record->verified_at);

        $this->assertDatabaseHas('audit_events', [
            'action' => AuditAction::PaymentConfirmed->value,
            'actor_type' => 'user',
            'subject_id' => (string) $booking->id,
        ]);
    }

    public function test_ac_18_reject_releases_seats_and_stores_the_reason(): void
    {
        $booking = $this->needsReview(participants: 2);
        $seatsBefore = $this->departure->refresh()->seats_taken;

        $this->actingAsTenant($this->a)
            ->post(route('bookings.reject', $booking), ['alasan' => '  Nominal   tidak sesuai '])
            ->assertRedirect()
            ->assertSessionHas('toast', 'Bukti ditolak. Kuota 2 orang dibuka lagi.');

        $this->assertSame(BookingStatus::Rejected, $booking->refresh()->status);
        $this->assertSame($seatsBefore - 2, $this->departure->refresh()->seats_taken);

        $record = PaymentRecord::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('rejected', $record->decision->value);
        $this->assertSame('Nominal tidak sesuai', $record->reject_reason);

        $event = AuditEvent::query()->where('action', AuditAction::PaymentRejected->value)->firstOrFail();
        $this->assertSame('Nominal tidak sesuai', $event->after['reject_reason']);
    }

    public function test_ac_18_reject_reason_over_200_chars_is_refused(): void
    {
        $booking = $this->needsReview();

        $this->actingAsTenant($this->a)
            ->post(route('bookings.reject', $booking), ['alasan' => str_repeat('x', 201)])
            ->assertSessionHasErrors('alasan');

        $this->assertSame(BookingStatus::NeedsReview, $booking->refresh()->status);
    }

    public function test_ac_16_verify_targets_appear_only_for_needs_review(): void
    {
        $needsReview = $this->needsReview();
        $awaiting = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::AwaitingPayment);

        $reviewDetail = $this->detailProps($this->a, $needsReview);
        $this->assertTrue($reviewDetail['booking']['can_verify']);
        $this->assertTrue($reviewDetail['booking']['payment']['has_evidence']);

        $awaitingDetail = $this->detailProps($this->a, $awaiting);
        $this->assertFalse($awaitingDetail['booking']['can_verify']);
    }

    public function test_ac_20_a_second_confirm_is_idempotent(): void
    {
        $booking = $this->needsReview();

        $this->actingAsTenant($this->a)->post(route('bookings.confirm', $booking))->assertRedirect();
        $this->actingAsTenant($this->a)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('notice', 'Pesanan ini sudah diproses.');

        $this->assertSame(BookingStatus::Paid, $booking->refresh()->status);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::PaymentConfirmed->value)->count());
    }

    public function test_ac_20_rejecting_an_already_confirmed_booking_does_nothing(): void
    {
        $booking = $this->needsReview();
        $this->actingAsTenant($this->a)->post(route('bookings.confirm', $booking))->assertRedirect();

        $this->actingAsTenant($this->a)
            ->post(route('bookings.reject', $booking))
            ->assertSessionHas('notice', 'Pesanan ini sudah diproses.');

        $this->assertSame(BookingStatus::Paid, $booking->refresh()->status);
    }

    public function test_ac_19_the_seller_streams_their_own_evidence(): void
    {
        $booking = $this->needsReview();

        $this->actingAsTenant($this->a)
            ->get(route('bookings.evidence', $booking))
            ->assertOk();
    }

    public function test_ac_19_another_seller_cannot_reach_the_evidence_or_verify(): void
    {
        $b = $this->seller('bahari', 'Bahari Trip');
        $booking = $this->needsReview();

        $this->actingAsTenant($b)->get(route('bookings.evidence', $booking))->assertNotFound();
        $this->actingAsTenant($b)->post(route('bookings.confirm', $booking))->assertNotFound();
        $this->actingAsTenant($b)->post(route('bookings.reject', $booking))->assertNotFound();

        // The current organization is B's after the requests; assert via the database.
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => BookingStatus::NeedsReview->value]);
    }

    public function test_ac_19_the_customer_streams_their_own_evidence_but_not_another_bookings(): void
    {
        $mine = $this->needsReview();
        $otherNoEvidence = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::AwaitingPayment);

        $this->get(route('public.booking.evidence.show', ['organization' => 'kencana', 'token' => $mine->access_token]))->assertOk();
        $this->get(route('public.booking.evidence.show', ['organization' => 'kencana', 'token' => $otherNoEvidence->access_token]))->assertNotFound();
    }
}
