<?php

namespace Tests\Feature\Trips;

use App\Audit\Actor;
use App\Models\AuditEvent;
use App\Models\TripDeparture;
use App\Trips\DeadlineCalculator;
use App\Trips\DeadlinePreset;
use App\Trips\DepartureService;
use App\Trips\Jobs\CloseDeparturesPastDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-21/AC-22 (calculator), AC-36, AC-37, ADR-0014, F-009 A11.
 */
class DeadlineAndBookabilityTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    public function test_ac_21_presets_are_computed_in_the_organization_zone_and_stored_in_utc(): void
    {
        $at = fn (DeadlinePreset $preset, string $zone) => DeadlineCalculator::compute($preset, '2024-10-24', '06:00', $zone)->format('Y-m-d H:i:s e');

        $this->assertSame('2024-10-21 16:59:59 UTC', $at(DeadlinePreset::H3, 'Asia/Jakarta'));
        $this->assertSame('2024-10-21 15:59:59 UTC', $at(DeadlinePreset::H3, 'Asia/Makassar'));
        $this->assertSame('2024-10-21 14:59:59 UTC', $at(DeadlinePreset::H3, 'Asia/Jayapura'));
        $this->assertSame('2024-10-23 22:00:00 UTC', $at(DeadlinePreset::SameDay, 'Asia/Jakarta'));
        $this->assertSame('2024-10-17 16:59:59 UTC', $at(DeadlinePreset::H7, 'Asia/Jakarta'));
    }

    public function test_ac_22_same_day_is_unavailable_without_a_meeting_time(): void
    {
        $this->assertNull(DeadlineCalculator::compute(DeadlinePreset::SameDay, '2024-10-24', null, 'Asia/Jakarta'));
        $this->assertNotContains(DeadlinePreset::SameDay, DeadlineCalculator::availablePresets(null));
        $this->assertContains(DeadlinePreset::SameDay, DeadlineCalculator::availablePresets('06:00'));
        $this->assertSame('2024-10-24 23:59:59', DeadlineCalculator::latestAllowed('2024-10-24', null, 'Asia/Jakarta')->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_same_day_before_a_meeting_just_after_midnight_falls_on_the_previous_day(): void
    {
        $deadline = DeadlineCalculator::compute(DeadlinePreset::SameDay, '2024-10-24', '00:30', 'Asia/Jakarta');

        $this->assertSame('2024-10-23 23:30', $deadline->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_ac_36_the_deadline_is_authoritative_at_request_time(): void
    {
        [$a] = $this->createTenants();
        $this->freezeClock();
        $trip = $this->tripFor($a, [], published: true);
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->asTenant($a, function () use ($departure) {
            $departure = TripDeparture::query()->with('trip.organization')->find($departure->id);

            $this->assertTrue($departure->isBookable(CarbonImmutable::parse('2024-10-21 23:59:30', 'Asia/Jakarta')));
            $this->assertFalse($departure->isBookable(CarbonImmutable::parse('2024-10-22 00:00:00', 'Asia/Jakarta')));
            // Still "open" in the database: no job has run.
            $this->assertSame('open', $departure->status->value);

            $this->assertSame(1, TripDeparture::query()->bookable('2024-10-21', CarbonImmutable::parse('2024-10-21 23:59:30', 'Asia/Jakarta'))->count());
            $this->assertSame(0, TripDeparture::query()->bookable('2024-10-22', CarbonImmutable::parse('2024-10-22 00:00:00', 'Asia/Jakarta'))->count());
        });
    }

    public function test_ac_36_bookable_needs_a_published_scheduled_trip_open_seats_and_a_future_start(): void
    {
        [$a] = $this->createTenants();
        $this->freezeClock();
        $published = $this->tripFor($a, [], published: true);
        $draft = $this->tripFor($a);

        $cases = [
            'bookable' => [$this->departureFor($a, $published, '2024-10-24'), true],
            'draft trip' => [$this->departureFor($a, $draft, '2024-10-24'), false],
            'full' => [$this->departureFor($a, $published, '2024-10-25', seatsTaken: 16), false],
            'closed' => [$this->departureFor($a, $published, '2024-10-26', ['status' => 'closed', 'closed_reason' => 'manual']), false],
        ];

        $this->asTenant($a, function () use ($cases) {
            foreach ($cases as $name => [$departure, $expected]) {
                $this->assertSame($expected, TripDeparture::query()->with('trip.organization')->find($departure->id)->isBookable(), $name);
            }

            $this->assertSame([$cases['bookable'][0]->id], TripDeparture::query()->bookable('2024-10-10')->pluck('id')->all());
        });
    }

    public function test_ac_37_the_job_closes_due_departures_with_a_system_audit_event_and_is_idempotent(): void
    {
        [$a, $b] = $this->createTenants();
        $this->freezeClock('2024-10-22 00:00:30');
        $dueA = $this->departureFor($a, $this->tripFor($a), '2024-10-24');
        $dueB = $this->departureFor($b, $this->tripFor($b), '2024-10-24');
        $later = $this->departureFor($a, $this->tripFor($a), '2024-10-31');

        $this->assertSame(2, app(CloseDeparturesPastDeadline::class)());
        $this->assertSame(0, app(CloseDeparturesPastDeadline::class)());

        foreach ([$dueA, $dueB] as $departure) {
            $fresh = $this->freshDeparture($departure);
            $this->assertSame(['closed', 'deadline'], [$fresh->status->value, $fresh->closed_reason->value]);
        }
        $this->assertSame('open', $this->freshDeparture($later)->status->value);

        $events = AuditEvent::acrossOrganizations()->where('action', 'departure.status_changed')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertEqualsCanonicalizing([$a->id(), $b->id()], $events->pluck('organization_id')->all());

        foreach ($events as $event) {
            $this->assertSame('system', $event->actor_type);
            $this->assertSame(CloseDeparturesPastDeadline::NAME, $event->actor_id);
            $this->assertNull($event->ip);
            $this->assertEquals(['status' => 'open', 'closed_reason' => null], $event->before);
            $this->assertEquals(['status' => 'closed', 'closed_reason' => 'deadline'], $event->after);
        }
    }

    public function test_ac_37_the_job_runs_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', CloseDeparturesPastDeadline::NAME));

        $this->assertNotNull($event, 'The auto-close command is scheduled.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_ac_37_the_artisan_command_runs_the_job(): void
    {
        [$a] = $this->createTenants();
        $this->freezeClock('2024-10-22 00:00:30');
        $this->departureFor($a, $this->tripFor($a), '2024-10-24');

        $this->artisan(CloseDeparturesPastDeadline::NAME)->expectsOutput('Closed 1 departure(s).')->assertSuccessful();
    }

    public function test_a11_system_close_does_not_touch_a_departure_whose_deadline_moved(): void
    {
        [$a] = $this->createTenants();
        $this->freezeClock('2024-10-22 00:00:30');
        $departure = $this->departureFor($a, $this->tripFor($a), '2024-10-31');

        $closed = $this->asTenant($a, fn () => app(DepartureService::class)
            ->closeAtDeadline($departure->id, CarbonImmutable::now(), Actor::system('test')));

        $this->assertFalse($closed);
        $this->assertDatabaseCount('audit_events', 0);
    }
}
