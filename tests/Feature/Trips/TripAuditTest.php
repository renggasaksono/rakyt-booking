<?php

namespace Tests\Feature\Trips;

use App\Models\AuditEvent;
use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-38: one audit event per change, with organization, actor, subject,
 * event name, before and after values and timestamp (F-009 format).
 */
class TripAuditTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
    }

    /**
     * @return list<array{0: string, 1: ?array, 2: ?array}>
     */
    private function events(): array
    {
        return AuditEvent::acrossOrganizations()->orderBy('id')->get()
            ->map(fn (AuditEvent $event) => [$event->action, $event->before, $event->after])
            ->all();
    }

    public function test_ac_38_base_price_changes_are_audited(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['base_price' => 1_250_000]);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['base_price' => 1_300_000, 'name' => 'Baru'])->assertOk();
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['name' => 'Baru lagi'])->assertOk();

        $this->assertSame([['trip.base_price_changed', ['base_price' => 1_250_000], ['base_price' => 1_300_000]]], $this->events());

        $event = AuditEvent::acrossOrganizations()->sole();
        $this->assertSame([$a->id(), 'user', (string) $a->owner->id], [$event->organization_id, $event->actor_type, $event->actor_id]);
        $this->assertSame([$trip->getMorphClass(), (string) $trip->id], [$event->subject_type, $event->subject_id]);
        $this->assertNotNull($event->created_at);
    }

    public function test_ac_38_creating_a_trip_with_a_price_records_the_first_price(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Bromo', 'base_price' => 450_000])->assertCreated();

        $this->assertSame([['trip.base_price_changed', ['base_price' => null], ['base_price' => 450_000]]], $this->events());
    }

    public function test_ac_38_departure_create_change_and_delete_are_audited_one_event_per_aspect(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->postJson(route('trips.departures.store', $trip), ['start_date' => '2024-10-24', 'capacity' => 16])->assertRedirect();
        $departure = TripDeparture::acrossOrganizations()->sole();

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), [
            'capacity' => 18,
            'price_override' => 1_150_000,
            'booking_deadline_preset' => 'h1',
            'status' => 'closed',
        ])->assertRedirect();

        // Nothing changed: no events.
        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['capacity' => 18])->assertRedirect();

        $this->actingAsTenant($a)->deleteJson(route('trips.departures.destroy', [$trip, $departure]))->assertRedirect();

        $events = $this->events();
        $this->assertSame(['departure.created', 'departure.price_changed', 'departure.capacity_changed', 'departure.status_changed', 'departure.deadline_changed', 'departure.deleted'], array_column($events, 0));

        // jsonb does not keep key order.
        $this->assertEquals(['start_date' => '2024-10-24', 'end_date' => '2024-10-26', 'capacity' => 16, 'price_override' => null, 'booking_deadline_preset' => 'h3', 'booking_deadline_at' => '2024-10-21T16:59:59+00:00', 'status' => 'open', 'closed_reason' => null], $events[0][2]);
        $this->assertSame([['price_override' => null], ['price_override' => 1_150_000]], [$events[1][1], $events[1][2]]);
        $this->assertSame([['capacity' => 16], ['capacity' => 18]], [$events[2][1], $events[2][2]]);
        $this->assertEquals([['status' => 'open', 'closed_reason' => null], ['status' => 'closed', 'closed_reason' => 'manual']], [$events[3][1], $events[3][2]]);
        $this->assertEquals([
            ['booking_deadline_preset' => 'h3', 'booking_deadline_at' => '2024-10-21T16:59:59+00:00'],
            ['booking_deadline_preset' => 'h1', 'booking_deadline_at' => '2024-10-23T16:59:59+00:00'],
        ], [$events[4][1], $events[4][2]]);
        $this->assertSame(18, $events[5][1]['capacity']);
        $this->assertNull($events[5][2]);
    }

    public function test_ac_38_quick_edit_changes_are_audited(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->patchJson(route('trips.departures.quick', [$trip, $departure]), ['price' => 1_350_000, 'capacity' => 20, 'status' => 'open'])->assertRedirect();

        $this->assertSame(['departure.price_changed', 'departure.capacity_changed'], array_column($this->events(), 0));
    }

    public function test_ac_38_rejected_changes_leave_no_audit_event(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 14);

        $this->actingAsTenant($a)->patchJson(route('trips.departures.quick', [$trip, $departure]), ['price' => 1_350_000, 'capacity' => 10, 'status' => 'open'])->assertUnprocessable();

        $this->assertSame([], $this->events());
        $this->assertNull($this->freshDeparture($departure)->price_override);
    }
}
