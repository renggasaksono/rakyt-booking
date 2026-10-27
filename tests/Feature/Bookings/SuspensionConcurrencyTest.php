<?php

namespace Tests\Feature\Bookings;

use App\Models\AuditEvent;
use App\Models\TripDeparture;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\Concurrency\RunsSecondProcess;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-014 AC-3 (ADR-0040): a booking racing the admin's suspension. Both take
 * the organization row lock first, so one waits for the other: a booking
 * submitted while the suspension is being written is refused, and a
 * suspension issued while a booking is being written lands after it.
 *
 * One side runs in a second process (tests/Support/Concurrency); rows must be
 * committed, so the schema is rebuilt after this class. The real clock runs.
 */
class SuspensionConcurrencyTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, RunsSecondProcess;

    private Tenant $a;

    private User $admin;

    private TripDeparture $departure;

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
        $this->admin = $this->platformAdmin();
        $trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $trip, CarbonImmutable::now('Asia/Jakarta')->addDays(14)->toDateString(), ['capacity' => 16]);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_ac_3_a_booking_submitted_while_the_seller_is_being_suspended_is_refused(): void
    {
        $suspension = $this->startProcess('suspend.php', [$this->a->id(), $this->admin->id, 1500]);
        $this->waitForOutput($suspension, 'locked:1');

        // The page check still sees the committed "active"; the booking then waits
        // for the organization lock and finds the seller suspended.
        $response = $this->from($this->formUrl('kencana', 'karimunjawa-3h2m', $this->departure->public_id))
            ->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($this->departure));
        $suspension->wait();

        $this->assertStringContainsString('suspended:1', $suspension->getOutput());
        $response->assertSessionHasErrors('departure');
        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertTrue($this->a->organization->refresh()->isSuspended());
    }

    public function test_ac_3_a_suspension_issued_while_a_booking_is_being_written_lands_after_it(): void
    {
        $booking = $this->startProcess('book.php', [$this->departure->id, $this->a->id(), 2, 1_250_000, 1500]);
        $this->waitForOutput($booking, 'locked:1');

        // The admin waits for the booking's organization lock.
        $this->actingAsConfirmedAdmin($this->admin)
            ->post(route('admin.sellers.suspend', $this->a->id()), ['alasan' => 'Laporan penipuan'])
            ->assertSessionHas('toast');
        $booking->wait();

        $this->assertStringContainsString('booked:1', $booking->getOutput());
        $this->assertSame(1, $this->bookingCount());
        $this->assertSame(2, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(
            ['booking.created', 'organization.suspended'],
            AuditEvent::acrossOrganizations()->orderBy('id')->pluck('action')->all(),
        );
    }
}
