<?php

namespace Tests\Feature\Trips;

use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-39 (ADR-0006): a seller reducing seats and a customer booking the
 * last seats at the same moment — exactly one succeeds, never overbooked.
 *
 * The customer books from a second process (its own connection), so rows must
 * be committed: this class does not wrap tests in a transaction, and the next
 * test class rebuilds the schema (audit events are append-only and cannot be
 * cleaned up row by row).
 */
class CapacityConcurrencyTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

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

    public function test_ac_39_customer_books_first_and_the_capacity_cut_is_rejected(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->asTenant($a, fn () => TripDeparture::factory()->withSeatsTaken(14)->create(['trip_id' => $trip->id, 'capacity' => 16]));

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
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->asTenant($a, fn () => TripDeparture::factory()->withSeatsTaken(14)->create(['trip_id' => $trip->id, 'capacity' => 16]));

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

        $this->assertStringContainsString('booked:0', $customer->getOutput());
        $this->assertSeats($departure, capacity: 14, taken: 14);
        $this->assertDatabaseHas('audit_events', ['action' => 'departure.capacity_changed']);
    }

    private function bookInAnotherConnection(TripDeparture $departure, int $seats, int $holdMs): Process
    {
        $connection = config('database.connections.pgsql');

        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/Concurrency/take_seats.php'), (string) $departure->id, (string) $departure->organization_id, (string) $seats, (string) $holdMs],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_URL' => '',
                'DB_HOST' => (string) $connection['host'],
                'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => (string) $connection['database'],
                'DB_USERNAME' => (string) $connection['username'],
                'DB_PASSWORD' => (string) $connection['password'],
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'LOG_CHANNEL' => 'null',
            ],
            timeout: 30,
        );
        $process->start();

        return $process;
    }

    private function waitForOutput(Process $process, string $line): void
    {
        $deadline = microtime(true) + 15;

        while (! str_contains($process->getOutput(), $line)) {
            if (! $process->isRunning() || microtime(true) > $deadline) {
                $process->stop();
                $this->fail("Customer process did not print [{$line}]. Output: {$process->getOutput()} Errors: {$process->getErrorOutput()}");
            }

            usleep(20_000);
        }
    }

    private function assertSeats(TripDeparture $departure, int $capacity, int $taken): void
    {
        $fresh = $this->freshDeparture($departure);

        $this->assertSame([$capacity, $taken], [$fresh->capacity, $fresh->seats_taken]);
        $this->assertLessThanOrEqual($fresh->capacity, $fresh->seats_taken);
    }
}
