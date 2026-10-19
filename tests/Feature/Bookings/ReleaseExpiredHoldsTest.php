<?php

namespace Tests\Feature\Bookings;

use App\Bookings\BookingStatus;
use App\Bookings\CancelReason;
use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-005 section D: the expiry job (AC-24, AC-25), the one "hold active" rule
 * (AC-27). The clock starts at Thursday 2024-10-10 10:00 WIB.
 */
class ReleaseExpiredHoldsTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
    }

    private function release(): int
    {
        return app(ReleaseExpiredHolds::class)();
    }

    public function test_ac_24_an_expired_hold_is_released_with_its_seats_and_audited_as_the_system(): void
    {
        $booking = $this->expiredHoldFor($this->a, $this->departure, 3);
        $this->bookingFor($this->a, $this->departure, 2);

        $this->assertSame(1, $this->release());

        $fresh = Booking::acrossOrganizations()->find($booking->id);
        $this->assertSame([BookingStatus::Cancelled, CancelReason::HoldExpired], [$fresh->status, $fresh->cancel_reason]);
        $this->assertSame(2, $this->freshDeparture($this->departure)->seats_taken);

        $event = AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->sole();
        $this->assertSame(['system', ReleaseExpiredHolds::NAME, null], [$event->actor_type, $event->actor_id, $event->ip]);
        $this->assertSame([$this->a->organization->id, (string) $booking->id], [$event->organization_id, $event->subject_id]);
        $this->assertEquals(['status' => 'awaiting_payment', 'cancel_reason' => null], $event->before);
        $this->assertEquals(['status' => 'cancelled', 'cancel_reason' => 'hold_expired'], $event->after);
    }

    public function test_ac_24_running_twice_gives_the_same_end_state_and_one_event(): void
    {
        $this->expiredHoldFor($this->a, $this->departure, 3);

        $this->assertSame(1, $this->release());
        $this->assertSame(0, $this->release());

        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->count());
    }

    public function test_ac_24_holds_of_every_organization_are_released_each_as_its_own(): void
    {
        $b = $this->seller('bahari', 'Bahari');
        $bDeparture = $this->departureFor($b, $this->bookableTrip($b), '2024-10-24');
        $this->expiredHoldFor($this->a, $this->departure, 1);
        $this->expiredHoldFor($b, $bDeparture, 2);

        $this->assertSame(2, $this->release());

        $this->assertEqualsCanonicalizing(
            [$this->a->organization->id, $b->organization->id],
            AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->pluck('organization_id')->all(),
        );
        $this->assertSame([0, 0], [$this->freshDeparture($this->departure)->seats_taken, $this->freshDeparture($bDeparture)->seats_taken]);
    }

    public function test_ac_24_the_command_is_scheduled_every_minute_on_one_server_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', ReleaseExpiredHolds::NAME));

        $this->assertNotNull($event, 'The expiry command is scheduled.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);

        $this->expiredHoldFor($this->a, $this->departure, 1);
        $this->artisan(ReleaseExpiredHolds::NAME)->expectsOutput('Released 1 expired seat hold(s).')->assertSuccessful();
    }

    public function test_ac_24_the_job_is_the_only_new_allowlist_entry(): void
    {
        $this->assertSame([
            'app/Trips/Jobs/CloseDeparturesPastDeadline.php',
            'app/Bookings/Jobs/ReleaseExpiredHolds.php',
        ], config('rakyt.cross_organization_allowlist'));
    }

    public function test_ac_25_the_job_never_releases_reviews_paid_cancelled_or_active_holds(): void
    {
        $past = CarbonImmutable::now()->subHour();
        $review = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::NeedsReview, ['hold_expires_at' => $past]);
        $paid = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid, ['hold_expires_at' => $past]);
        $cancelled = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Cancelled, ['hold_expires_at' => $past]);
        $active = $this->bookingFor($this->a, $this->departure, 1);

        $this->assertSame(0, $this->release());

        foreach ([[$review, BookingStatus::NeedsReview], [$paid, BookingStatus::Paid], [$cancelled, BookingStatus::Cancelled], [$active, BookingStatus::AwaitingPayment]] as [$booking, $status]) {
            $this->assertSame($status, Booking::acrossOrganizations()->find($booking->id)->status);
        }

        $this->assertSame(3, $this->freshDeparture($this->departure)->seats_taken);
    }

    public function test_ac_25_ac_27_the_hold_is_active_until_exactly_two_hours(): void
    {
        $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($this->departure))->assertStatus(303);
        $booking = $this->latestBooking();
        $created = CarbonImmutable::now();

        $this->travelTo($created->addSeconds(2 * 3600 - 1));
        $this->assertTrue($booking->holdIsActive());
        $this->assertSame(0, $this->release());

        $this->travelTo($created->addHours(2));
        $this->assertFalse($booking->fresh()->holdIsActive(), 'Inactive before any job runs.');
        $this->assertSame(1, $this->release());
        $this->assertFalse(Booking::acrossOrganizations()->find($booking->id)->holdIsActive());
    }

    public function test_ac_27_hold_active_needs_awaiting_payment(): void
    {
        $now = CarbonImmutable::now();

        foreach (BookingStatus::cases() as $status) {
            $booking = new Booking;
            $booking->forceFill(['status' => $status, 'hold_expires_at' => $now->addHour()]);

            $this->assertSame($status === BookingStatus::AwaitingPayment, $booking->holdIsActive($now), $status->value);
        }
    }
}
