<?php

namespace Tests\Feature\Trips;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Models\TripPhoto;
use App\Trips\PublishChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-9–AC-13, AC-15, AC-16.
 */
class PublishTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
    }

    private function addPhoto($tenant, Trip $trip, int $position = 1): TripPhoto
    {
        return $this->asTenant($tenant, fn () => TripPhoto::create([
            'trip_id' => $trip->id,
            'path' => "org/{$tenant->organization->public_id}/trips/{$trip->public_id}/foto-{$position}.jpg",
            'position' => $position,
        ]));
    }

    private function checklistOf($tenant, Trip $trip): array
    {
        $items = [];

        $this->actingAsTenant($tenant)->get(route('trips.edit', $trip))
            ->assertInertia(function (AssertableInertia $page) use (&$items) {
                $items = $page->toArray()['props']['checklist'];
            });

        return $items;
    }

    public function test_ac_9_checklist_has_five_items_for_scheduled_trips(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['short_description' => null]);

        $checklist = $this->checklistOf($a, $trip);

        $this->assertSame(['cover_photo', 'name', 'base_price', 'departure', 'payment_instructions'], array_column($checklist['items'], 'key'));
        $this->assertSame([false, true, true, false, false], array_column($checklist['items'], 'done'));
        $this->assertSame([2, 5], [$checklist['done'], $checklist['total']]);
    }

    public function test_ac_9_request_date_trips_have_four_items(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['type' => 'private', 'booking_mode' => 'request_date']);

        $checklist = $this->checklistOf($a, $trip);

        $this->assertSame(['cover_photo', 'name', 'base_price', 'payment_instructions'], array_column($checklist['items'], 'key'));
        $this->assertSame(4, $checklist['total']);
    }

    public function test_ac_9_payment_item_follows_the_payment_readiness_seam_and_hides_the_link_until_it_exists(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        // Without a binding the default says "no method" (AC-9 test above); bind the fake before the first request.
        $this->withPaymentMethod();
        $this->assertTrue(collect($this->checklistOf($a, $trip)['items'])->firstWhere('key', 'payment_instructions')['done']);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('urls.payment_setup', null)
                ->where('copy.checklist.payment_link', 'atur rekening'));
    }

    public function test_ac_10_only_an_open_future_departure_with_a_future_deadline_counts(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departureItem = fn () => collect($this->checklistOf($a, $trip)['items'])->firstWhere('key', 'departure')['done'];

        $this->departureFor($a, $trip, '2024-10-24', ['status' => 'closed', 'closed_reason' => 'manual']);
        $this->departureFor($a, $trip, '2024-10-11', ['booking_deadline_at' => '2024-10-10 01:00:00']);
        $this->assertFalse($departureItem());

        $this->departureFor($a, $trip, '2024-10-31');
        $this->assertTrue($departureItem());
    }

    public function test_ac_10_the_departure_item_compares_deadlines_in_utc_whatever_the_php_zone(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        // Deadline 3 hours from now: still ahead.
        $this->departureFor($a, $trip, '2024-10-11', ['booking_deadline_at' => now()->utc()->addHours(3)]);
        $previous = date_default_timezone_get();

        try {
            // A non-UTC PHP zone used to shift the bound "now" by 7 hours.
            date_default_timezone_set('Asia/Jakarta');
            $this->asTenant($a, fn () => $this->assertTrue(app(PublishChecklist::class)->hasUpcomingOpenDeparture($trip->fresh())));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_ac_11_publish_is_refused_server_side_with_the_missing_items(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['base_price' => null]);

        $this->actingAsTenant($a)->postJson(route('trips.publish', $trip))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['publish' => 'Belum bisa terbit. Lengkapi: Foto sampul, Harga dasar, Minimal 1 keberangkatan, Instruksi pembayaran.']);

        $this->assertSame('draft', $this->freshTrip($trip)->status->value);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_ac_11_the_helper_copy_counts_remaining_steps(): void
    {
        $this->assertSame('Lengkapi 1 langkah lagi untuk terbit', __('trips.checklist.remaining', ['n' => 1]));
        $this->assertSame('Siap terbit · 4/5', __('trips.checklist.title', ['done' => 4, 'total' => 5]));
    }

    public function test_ac_12_a_complete_trip_is_published_and_audited(): void
    {
        [$a] = $this->createTenants();
        $this->withPaymentMethod();
        $trip = $this->tripFor($a);
        $this->addPhoto($a, $trip);
        $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->post(route('trips.publish', $trip))
            ->assertRedirect()
            ->assertSessionHas('toast', __('trips.notices.published'));

        $fresh = $this->freshTrip($trip);
        $this->assertSame('published', $fresh->status->value);
        $this->assertNotNull($fresh->published_at);

        $event = AuditEvent::acrossOrganizations()->where('action', 'trip.published')->sole();
        $this->assertSame(['user', (string) $a->owner->id, $a->id()], [$event->actor_type, $event->actor_id, $event->organization_id]);
        $this->assertSame(['status' => 'draft'], $event->before);
        $this->assertSame(['status' => 'published'], $event->after);
    }

    public function test_ac_12_a_request_date_trip_publishes_without_departures(): void
    {
        [$a] = $this->createTenants();
        $this->withPaymentMethod();
        $trip = $this->tripFor($a, ['type' => 'private', 'booking_mode' => 'request_date']);
        $this->addPhoto($a, $trip);

        $this->actingAsTenant($a)->postJson(route('trips.publish', $trip))->assertRedirect();
        $this->assertSame('published', $this->freshTrip($trip)->status->value);
    }

    public function test_ac_13_preview_shows_the_lowest_open_future_price_and_next_departure(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['name' => 'Karimunjawa 3H2M Open Trip', 'base_price' => 1_250_000]);
        $this->addPhoto($a, $trip);
        $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 2);
        $this->departureFor($a, $trip, '2024-11-14', ['price_override' => 1_150_000]);
        $this->departureFor($a, $trip, '2024-11-21', ['price_override' => 900_000, 'status' => 'closed', 'closed_reason' => 'manual']);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('preview.name', 'Karimunjawa 3H2M Open Trip')
                ->where('preview.from_price', 1_150_000)
                ->where('preview.next_departure', ['start_date' => '2024-10-24', 'seats_left' => 14])
                ->where('preview.cover_url', fn ($url) => str_contains($url, 'foto-1.jpg')));
    }

    public function test_ac_13_preview_falls_back_to_the_base_price(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['base_price' => 1_250_000]);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('preview.from_price', 1_250_000)
                ->where('preview.next_departure', null)
                ->where('preview.cover_url', null));
    }

    public function test_ac_15_unpublish_returns_the_trip_to_draft_and_is_audited(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);
        $departure = $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 4);

        $this->actingAsTenant($a)->post(route('trips.unpublish', $trip))->assertSessionHas('toast', __('trips.notices.unpublished'));

        $this->assertSame('draft', $this->freshTrip($trip)->status->value);
        // Bookings keep their seats; nothing about the departure changes.
        $this->assertSame([4, 'open'], [$this->freshDeparture($departure)->seats_taken, $this->freshDeparture($departure)->status->value]);

        $event = AuditEvent::acrossOrganizations()->where('action', 'trip.unpublished')->sole();
        $this->assertSame(['status' => 'published'], $event->before);
        $this->assertSame(['status' => 'draft'], $event->after);
    }

    public function test_ac_16_a_trip_without_bookings_is_deleted_with_its_departures_and_photos(): void
    {
        Storage::fake(config('rakyt.media.public_disk'));
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $photo = $this->addPhoto($a, $trip);
        Storage::disk(config('rakyt.media.public_disk'))->put($photo->path, 'x');
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->delete(route('trips.destroy', $trip))->assertRedirect(route('trips.index'));

        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
        $this->assertDatabaseMissing('trip_departures', ['id' => $departure->id]);
        $this->assertDatabaseMissing('trip_photos', ['id' => $photo->id]);
        Storage::disk(config('rakyt.media.public_disk'))->assertMissing($photo->path);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'departure.deleted')->count());
    }

    public function test_ac_16_a_trip_with_a_booking_cannot_be_deleted(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);
        $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 1);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('trip.can_delete', false));

        $this->actingAsTenant($a)->deleteJson(route('trips.destroy', $trip))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trip' => __('trips.errors.trip_has_bookings')]);
        $this->assertDatabaseHas('trips', ['id' => $trip->id]);
    }
}
