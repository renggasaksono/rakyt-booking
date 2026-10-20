<?php

namespace Tests\Feature\Payments;

use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\Concurrency\RunsSecondProcess;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 AC-20 (ADR-0026 §3): the seller's cancel and a payment verification on
 * the same needs_review booking are serialised by the booking lock. The cancel
 * runs in a second process holding its transaction open; the verify here must
 * wait, then find the booking no longer needs_review and change nothing. Seats
 * move once; the invariant is checked after every test. Rows are committed, so
 * tests are not wrapped in a transaction.
 */
class VerifyConcurrencyTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, RunsSecondProcess;

    private Tenant $a;

    private TripDeparture $departure;

    private Booking $booking;

    /**
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.private_disk'));
        $this->a = $this->seller();
        $this->departure = $this->departureFor($this->a, $this->bookableTrip($this->a), CarbonImmutable::now('Asia/Jakarta')->addDays(14)->toDateString(), ['capacity' => 4]);
        $this->booking = $this->bookingFor($this->a, $this->departure, 2, BookingStatus::NeedsReview);
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->for($this->booking)->withEvidence()->create());
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    private function assertCancelledOnce(): void
    {
        $fresh = Booking::acrossOrganizations()->findOrFail($this->booking->id);
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        // Seats dropped once (2 held → 0); no payment.confirmed / payment.rejected.
        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(0, AuditEvent::acrossOrganizations()
            ->whereIn('action', ['payment.confirmed', 'payment.rejected'])
            ->where('subject_id', (string) $this->booking->id)
            ->count());
    }

    public function test_ac_20_a_cancel_holds_the_lock_and_confirm_then_changes_nothing(): void
    {
        $job = $this->startProcess('cancel_booking.php', [$this->booking->id, $this->a->organization->id, $this->a->owner->id, 1500]);
        $this->waitForOutput($job, 'locked:1');

        $response = $this->actingAsTenant($this->a)->post(route('bookings.confirm', $this->booking));
        $job->wait();

        $this->assertStringContainsString('cancelled:1', $job->getOutput());
        $response->assertRedirect()->assertSessionHas('notice', 'Pesanan ini sudah diproses.');
        $this->assertCancelledOnce();
    }

    public function test_ac_20_a_cancel_holds_the_lock_and_reject_then_changes_nothing(): void
    {
        $job = $this->startProcess('cancel_booking.php', [$this->booking->id, $this->a->organization->id, $this->a->owner->id, 1500]);
        $this->waitForOutput($job, 'locked:1');

        $response = $this->actingAsTenant($this->a)->post(route('bookings.reject', $this->booking));
        $job->wait();

        $this->assertStringContainsString('cancelled:1', $job->getOutput());
        $response->assertRedirect()->assertSessionHas('notice', 'Pesanan ini sudah diproses.');
        $this->assertCancelledOnce();
    }
}
