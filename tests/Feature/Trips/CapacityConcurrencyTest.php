<?php

namespace Tests\Feature\Trips;

use App\Bookings\BookingStatus;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsTrips;
use Tests\Support\Concurrency\RunsSecondProcess;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-003 AC-39 (ADR-0006): a seller reducing seats and a customer booking the
 * last seats at the same moment — exactly one succeeds, never overbooked.
 * Since F-005 the customer books through the real BookingService (F-005 AC-21).
 *
 * The customer books from a second process (its own connection), so rows must
 * be committed: this class does not wrap tests in a transaction, and the next
 * test class rebuilds the schema (audit events are append-only and cannot be
 * cleaned up row by row).
 */
class CapacityConcurrencyTest extends TestCase
{
    use BuildsBookings, BuildsTrips, InteractsWithTenants, RefreshDatabase, RunsSecondProcess;

    /**
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    /**
     * @return array{0: Tenant, 1: Trip, 2: TripDeparture}
     */
    private function departureWith14Of16SeatsTaken(): array
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, published: true);
        $departure = $this->departureFor($a, $trip, CarbonImmutable::now('Asia/Jakarta')->addDays(14)->toDateString(), ['capacity' => 16]);
        $this->bookingFor($a, $departure, 14, BookingStatus::Paid);

        return [$a, $trip, $departure];
    }

    public function test_ac_39_customer_books_first_and_the_capacity_cut_is_rejected(): void
    {
        [$a, $trip, $departure] = $this->departureWith14Of16SeatsTaken();

        // The customer's transaction holds the row lock for a moment…
        $customer = $this->bookInAnotherConnection($departure, seats: 2, holdMs: 1500);
        $this->waitForOutput($customer, 'locked:1');

        // …so the seller's capacity change waits for it, then re-reads seats_taken.
        $response = $this->actingAsTenant($a)->patchJson(
            route('trips.departures.quick', [$trip, $departure]),
            ['price' => 1_250_000, 'capacity' => 14, 'status' => 'open'],
        );
        $customer->wait();

        $response->assertUnprocessable()->assertJsonValidationErrors(['capacity' => '16 terisi — tidak bisa di bawah 16']);
        $this->assertStringContainsString('booked:1', $customer->getOutput());
        $this->assertSeats($departure, capacity: 16, taken: 16);
        $this->assertDatabaseMissing('audit_events', ['action' => 'departure.capacity_changed']);
    }

    public function test_ac_39_seller_cuts_capacity_first_and_the_booking_is_rejected(): void
    {
        [$a, $trip, $departure] = $this->departureWith14Of16SeatsTaken();

        // The seller's change runs and keeps its lock until we commit…
        DB::beginTransaction();
        $this->actingAsTenant($a)->patchJson(
            route('trips.departures.quick', [$trip, $departure]),
            ['price' => 1_250_000, 'capacity' => 14, 'status' => 'open'],
        )->assertRedirect();

        // …while the customer tries to book the last two seats.
        $customer = $this->bookInAnotherConnection($departure, seats: 2, holdMs: 0);
        $this->waitForOutput($customer, 'started');
        usleep(300_000);
        DB::commit();
        $customer->wait();

        $this->assertStringContainsString('booked:0 departure: Tanggal ini sudah tidak bisa dipesan.', $customer->getOutput());
        $this->assertSeats($departure, capacity: 14, taken: 14);
        $this->assertDatabaseHas('audit_events', ['action' => 'departure.capacity_changed']);
    }

    private function bookInAnotherConnection(TripDeparture $departure, int $seats, int $holdMs): Process
    {
        return $this->startProcess('book.php', [$departure->id, $departure->organization_id, $seats, 1_250_000, $holdMs]);
    }

    private function assertSeats(TripDeparture $departure, int $capacity, int $taken): void
    {
        $fresh = $this->freshDeparture($departure);

        $this->assertSame([$capacity, $taken], [$fresh->capacity, $fresh->seats_taken]);
        $this->assertLessThanOrEqual($fresh->capacity, $fresh->seats_taken);
    }
}
