<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingStatus;
use App\Bookings\CancelReason;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\Concurrency\RunsSecondProcess;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-007 AC-31 (ADR-0006, ADR-0026 §3): the seller's cancel and the expiry job
 * on a hold that has just expired, each from its own database connection.
 * One side runs in a second process (tests/Support/Concurrency) and holds its
 * transaction open; the other runs here and must wait for the locks. Exactly
 * one of them changes the booking, one event is written, and seats_taken
 * drops once (the seat invariant is checked after every test).
 *
 * Rows must be committed, so this class does not wrap tests in a transaction
 * and the next test class rebuilds the schema. The real clock runs.
 */
class CancelConcurrencyTest extends TestCase
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

        $this->a = $this->seller();
        $this->departure = $this->departureFor($this->a, $this->bookableTrip($this->a), CarbonImmutable::now('Asia/Jakarta')->addDays(14)->toDateString(), ['capacity' => 4]);
        $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);
        // A hold that has just expired, not released yet.
        $this->booking = $this->expiredHoldFor($this->a, $this->departure, 2, CarbonImmutable::now()->subSecond());
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    private function assertChangedOnce(CancelReason $reason): void
    {
        $fresh = Booking::acrossOrganizations()->findOrFail($this->booking->id);

        $this->assertSame([BookingStatus::Cancelled, $reason], [$fresh->status, $fresh->cancel_reason]);
        $this->assertSame(1, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()
            ->whereIn('action', ['booking.cancelled', 'booking.hold_expired'])
            ->where('subject_id', (string) $this->booking->id)
            ->count());
    }

    public function test_ac_31_the_job_holds_the_locks_and_the_sellers_cancel_then_changes_nothing(): void
    {
        $job = $this->startProcess('release_holds.php', [1500]);
        $this->waitForOutput($job, 'locked:1');

        $response = $this->actingAsTenant($this->a)->post(route('bookings.cancel', $this->booking));
        $job->wait();

        $this->assertStringContainsString('released:1', $job->getOutput());
        $response->assertRedirect('/pesanan/'.$this->booking->public_id)->assertSessionHas('notice', 'Pesanan ini sudah tidak aktif.');
        $this->assertChangedOnce(CancelReason::HoldExpired);
    }

    public function test_ac_31_the_seller_holds_the_locks_and_the_job_then_releases_nothing(): void
    {
        $seller = $this->startProcess('cancel_booking.php', [$this->booking->id, $this->a->organization->id, $this->a->owner->id, 1500]);
        $this->waitForOutput($seller, 'locked:1');

        $released = app(ReleaseExpiredHolds::class)();
        $seller->wait();

        $this->assertStringContainsString('cancelled:1', $seller->getOutput());
        $this->assertSame(0, $released);
        $this->assertChangedOnce(CancelReason::Seller);
    }
}
