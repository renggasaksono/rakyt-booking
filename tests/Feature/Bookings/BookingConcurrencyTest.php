<?php

namespace Tests\Feature\Bookings;

use App\Bookings\BookingStatus;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\Concurrency\RunsSecondProcess;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-005 section C (ADR-0006): two customers, a booking and the expiry job,
 * each from its own database connection, at the same moment. One side runs in
 * a second process (tests/Support/Concurrency) and holds its transaction open;
 * the other is a real HTTP submit here, which must wait for the locks.
 *
 * Rows must be committed, so this class does not wrap tests in a transaction
 * and the next test class rebuilds the schema. The real clock runs (the
 * second process cannot share a frozen one). The seat invariant (AC-23) is
 * checked after every test (BuildsBookings).
 */
class BookingConcurrencyTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, RunsSecondProcess;

    private Tenant $a;

    private Trip $trip;

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
        $this->trip = $this->bookableTrip($this->a);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    private function departure(int $capacity, int $paidSeats = 0, int $daysAhead = 14): TripDeparture
    {
        $departure = $this->departureFor($this->a, $this->trip, CarbonImmutable::now('Asia/Jakarta')->addDays($daysAhead)->toDateString(), ['capacity' => $capacity]);

        if ($paidSeats > 0) {
            $this->bookingFor($this->a, $departure, $paidSeats, BookingStatus::Paid);
        }

        return $departure;
    }

    private function bookInAnotherConnection(TripDeparture $departure, int $participants, int $holdMs): Process
    {
        return $this->startProcess('book.php', [$departure->id, $departure->organization_id, $participants, 1_250_000, $holdMs]);
    }

    private function bookHere(TripDeparture $departure, int $participants): TestResponse
    {
        return $this->from($this->formUrl('kencana', 'karimunjawa-3h2m', $departure->public_id))
            ->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($departure, ['participants' => $participants]));
    }

    public function test_ac_19_two_customers_racing_for_the_last_seat_get_one_booking(): void
    {
        $departure = $this->departure(capacity: 16, paidSeats: 15);

        $other = $this->bookInAnotherConnection($departure, 1, holdMs: 1500);
        $this->waitForOutput($other, 'locked:1');

        // Waits for the other transaction's locks, then sees the seat gone.
        $response = $this->bookHere($departure, 1);
        $other->wait();

        $this->assertStringContainsString('booked:1', $other->getOutput());
        $response->assertSessionHasErrors(['departure' => 'Tanggal ini sudah tidak bisa dipesan.']);
        $this->assertSame('full', $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id)['departure']['state']);
        $this->assertSame(16, $this->freshDeparture($departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.created')->count());
    }

    public function test_ac_20_two_groups_racing_for_the_last_seats_get_one_booking(): void
    {
        $departure = $this->departure(capacity: 16, paidSeats: 13);

        $other = $this->bookInAnotherConnection($departure, 2, holdMs: 1500);
        $this->waitForOutput($other, 'locked:1');

        $response = $this->bookHere($departure, 2);
        $other->wait();

        $this->assertStringContainsString('booked:1', $other->getOutput());
        $response->assertSessionHasErrors(['participants' => 'Sisa kursi tinggal 1. Kurangi jumlah peserta.']);
        $this->assertSame(15, $this->freshDeparture($departure)->seats_taken);
    }

    public function test_ac_17_simultaneous_bookings_of_one_seller_get_different_numbers(): void
    {
        $first = $this->departure(capacity: 16);
        $second = $this->departure(capacity: 16, daysAhead: 15);

        $other = $this->bookInAnotherConnection($first, 1, holdMs: 1000);
        $this->waitForOutput($other, 'locked:1');

        $this->bookHere($second, 1)->assertStatus(303);
        $other->wait();

        $numbers = Booking::acrossOrganizations()->pluck('reference')->map(fn (string $reference) => substr($reference, -4))->sort()->values()->all();
        $this->assertSame(['0001', '0002'], $numbers);
    }

    public function test_ac_16_the_same_submission_key_from_two_connections_creates_one_booking(): void
    {
        $departure = $this->departure(capacity: 16);
        $key = (string) Str::uuid();

        $other = $this->startProcess('book.php', [$departure->id, $departure->organization_id, 2, 1_250_000, 1500, $key]);
        $this->waitForOutput($other, 'locked:1');

        // Waits for the organization lock, then finds the first submit's booking.
        $response = $this->from($this->formUrl('kencana', 'karimunjawa-3h2m', $departure->public_id))
            ->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($departure, ['submission_key' => $key]));
        $other->wait();

        $this->assertStringContainsString('booked:1', $other->getOutput());
        $booking = Booking::acrossOrganizations()->sole();
        $response->assertStatus(303)->assertRedirect(route('public.booking.show', ['organization' => 'kencana', 'token' => $booking->access_token]));
        $this->assertSame(2, $this->freshDeparture($departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.created')->count());
    }

    public function test_ac_22_a_booking_and_the_expiry_job_on_the_same_seats_lose_no_update(): void
    {
        $departure = $this->departure(capacity: 4, paidSeats: 2);
        $this->expiredHoldFor($this->a, $departure, 2);

        // The job releases the hold and keeps its locks for a moment…
        $job = $this->startProcess('release_holds.php', [1500]);
        $this->waitForOutput($job, 'locked:1');

        // …while a customer books the seats it is giving back.
        $response = $this->bookHere($departure, 2);
        $job->wait();

        $response->assertStatus(303);
        $this->assertStringContainsString('released:1', $job->getOutput());
        $this->assertSame(4, $this->freshDeparture($departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->count());
    }

    public function test_ac_22_the_expiry_job_waiting_for_a_booking_that_released_inline_changes_nothing(): void
    {
        $departure = $this->departure(capacity: 4, paidSeats: 2);
        $this->expiredHoldFor($this->a, $departure, 2);

        // The booking releases the expired hold inline and holds its locks…
        $other = $this->bookInAnotherConnection($departure, 2, holdMs: 1500);
        $this->waitForOutput($other, 'locked:1');

        // …so the job waits, then finds nothing left to release.
        $released = app(ReleaseExpiredHolds::class)();
        $other->wait();

        $this->assertStringContainsString('booked:1', $other->getOutput());
        $this->assertSame(0, $released);
        $this->assertSame(4, $this->freshDeparture($departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->count());
    }

    public function test_ac_24_two_job_runs_at_once_release_each_hold_once(): void
    {
        $departure = $this->departure(capacity: 4);
        $this->expiredHoldFor($this->a, $departure, 2);

        $job = $this->startProcess('release_holds.php', [1500]);
        $this->waitForOutput($job, 'locked:1');

        $released = app(ReleaseExpiredHolds::class)();
        $job->wait();

        $this->assertSame(0, $released);
        $this->assertSame(0, $this->freshDeparture($departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->count());
    }
}
