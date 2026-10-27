<?php

namespace Tests\Feature\Bookings;

use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\Concurrency\RunsSecondProcess;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-014 AC-3, AC-6 (ADR-0040): a booking or an evidence upload racing the
 * admin's suspension. All take the organization row lock first (the upload a
 * shared one), so one waits for the other: a booking or upload submitted while
 * the suspension is being written is refused, and a suspension issued while a
 * booking or upload is being written lands after it.
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

    public function test_ac_6_an_upload_submitted_while_the_seller_is_being_suspended_is_refused(): void
    {
        Storage::fake(config('rakyt.media.private_disk'));
        $booking = $this->awaiting();
        $suspension = $this->startProcess('suspend.php', [$this->a->id(), $this->admin->id, 1500]);
        $this->waitForOutput($suspension, 'locked:1');

        // The controller still sees the committed "active"; BookingService then waits
        // for its shared lock on the organization and finds the seller suspended.
        $response = $this->post(
            route('public.booking.evidence', ['organization' => 'kencana', 'token' => $booking->access_token]),
            ['bukti' => UploadedFile::fake()->image('bukti.jpg')],
        );
        $suspension->wait();

        $this->assertStringContainsString('suspended:1', $suspension->getOutput());
        $response->assertStatus(303)->assertSessionHas('notice', __('booking.payment.errors.seller_unavailable'));
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->refresh()->status);
        $this->assertSame(0, PaymentRecord::acrossOrganizations()->count());
        $this->assertSame([], Storage::disk(config('rakyt.media.private_disk'))->allFiles());
    }

    public function test_ac_6_a_suspension_issued_while_an_upload_is_being_written_lands_after_it(): void
    {
        $booking = $this->awaiting();
        $upload = $this->startProcess('upload.php', [$booking->id, $this->a->id(), 1500]);
        $this->waitForOutput($upload, 'locked:1');

        // The admin's FOR UPDATE waits for the upload's shared lock.
        $this->actingAsConfirmedAdmin($this->admin)
            ->post(route('admin.sellers.suspend', $this->a->id()), ['alasan' => 'Laporan penipuan'])
            ->assertSessionHas('toast');
        $upload->wait();

        $this->assertStringContainsString('uploaded:1', $upload->getOutput());
        $this->assertSame(BookingStatus::NeedsReview, $booking->refresh()->status);
        $this->assertTrue($this->a->organization->refresh()->isSuspended());
        $this->assertSame(
            ['payment.evidence_uploaded', 'organization.suspended'],
            AuditEvent::acrossOrganizations()->orderBy('id')->pluck('action')->all(),
        );
    }

    private function awaiting(): Booking
    {
        return $this->bookingFor($this->a, $this->departure, 2, BookingStatus::AwaitingPayment, [
            'created_at' => CarbonImmutable::now(),
            'hold_expires_at' => CarbonImmutable::now()->addMinutes(120),
        ]);
    }
}
