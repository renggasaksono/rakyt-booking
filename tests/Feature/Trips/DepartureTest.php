<?php

namespace Tests\Feature\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\Contracts\DepartureBookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-17–AC-30 (server side). Clock: Thu 2024-10-10 10:00 WIB.
 */
class DepartureTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
    }

    private function store(Trip $trip, array $payload)
    {
        return $this->postJson(route('trips.departures.store', $trip), $payload);
    }

    public function test_ac_17_end_date_is_derived_from_the_duration_and_deadline_defaults_to_the_organization_preset(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a);
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16])->assertRedirect();

        $departure = TripDeparture::acrossOrganizations()->sole();
        $this->assertSame('2024-10-26', $departure->end_date->toDateString());
        $this->assertSame('h3', $departure->booking_deadline_preset->value);
        $this->assertSame('2024-10-21 23:59:59', $departure->booking_deadline_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertNull($departure->price_override);
        $this->assertSame('open', $departure->status->value);
        $this->assertSame(0, $departure->seats_taken);
    }

    public function test_ac_17_the_organization_default_preset_is_used(): void
    {
        [$a] = $this->createTenants();
        $a->organization->update(['default_booking_deadline_preset' => 'h7']);
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a);
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16])->assertRedirect();

        $this->assertSame('h7', TripDeparture::acrossOrganizations()->sole()->booking_deadline_preset->value);
    }

    public function test_ac_17_the_editor_prefills_seats_from_the_previous_departure(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-24', ['capacity' => 12]);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('copyTemplate.capacity', 12)
                ->where('context.default_deadline_preset', 'h3'));
    }

    public function test_ac_17_a_scheduled_trip_needs_a_duration_first(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['duration_label' => null, 'duration_days' => null, 'duration_nights' => null]);

        $this->actingAsTenant($a);
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration' => __('trips.errors.needs_duration')]);
    }

    public function test_ac_17_request_date_trips_have_no_departures(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['type' => 'private', 'booking_mode' => 'request_date']);

        $this->actingAsTenant($a);
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16])->assertUnprocessable();
        $this->assertDatabaseCount('trip_departures', 0);
    }

    public function test_ac_18_past_start_dates_are_rejected_and_same_dates_are_allowed(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a);
        $this->store($trip, ['start_date' => '2024-10-09', 'capacity' => 16])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => __('trips.errors.start_date_past')]);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16])->assertRedirect();
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 10])->assertRedirect();

        $this->assertSame(2, TripDeparture::acrossOrganizations()->whereDate('start_date', '2024-10-24')->count());
    }

    public function test_ac_19_seats_are_a_whole_number_from_1_to_999(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->actingAsTenant($a);

        foreach ([0, 1000, 2.5, 'banyak'] as $bad) {
            $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('capacity');
        }

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 1])->assertRedirect();
        $this->store($trip, ['start_date' => '2024-10-25', 'capacity' => 999])->assertRedirect();
    }

    public function test_ac_20_empty_price_follows_the_base_price_and_a_price_overrides_it(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['base_price' => 1_250_000]);
        $base = $this->departureFor($a, $trip, '2024-10-24');
        $special = $this->departureFor($a, $trip, '2024-11-14', ['price_override' => 1_150_000]);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['base_price' => 1_300_000])->assertOk();

        $this->asTenant($a, function () use ($base, $special) {
            $this->assertSame(1_300_000, $base->fresh()->effectivePrice());
            $this->assertSame(1_150_000, $special->fresh()->effectivePrice());
        });

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $special]), ['price_override' => null])->assertRedirect();
        $this->asTenant($a, fn () => $this->assertSame(1_300_000, $special->fresh()->effectivePrice()));
    }

    public function test_ac_20_price_override_range_is_checked(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->actingAsTenant($a);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'price_override' => 500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_override' => __('trips.errors.price_range')]);
    }

    public function test_ac_21_presets_resolve_to_real_dates_and_times(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => '06:00']);
        $this->actingAsTenant($a);

        $expected = [
            'same_day' => '2024-10-24 05:00:00',
            'h1' => '2024-10-23 23:59:59',
            'h3' => '2024-10-21 23:59:59',
            'h7' => '2024-10-17 23:59:59',
        ];

        foreach ($expected as $preset => $local) {
            $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => $preset])->assertRedirect();

            $departure = TripDeparture::acrossOrganizations()->latest('id')->first();
            $this->assertSame($local, $departure->booking_deadline_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'), $preset);
        }
    }

    public function test_ac_21_the_chosen_deadline_is_shown_as_an_indonesian_date(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.upcoming.0.deadline_long', 'Senin, 21 Okt pukul 23.59')
                ->where('departures.upcoming.0.deadline_short', '21 Okt 23.59')
                ->where('departures.upcoming.0.booking_deadline_local', '2024-10-21 23:59'));
    }

    public function test_ac_21_custom_deadline_is_accepted_in_the_organization_zone(): void
    {
        [$a] = $this->createTenants();
        $a->organization->update(['timezone' => 'Asia/Makassar']);
        $trip = $this->tripFor($a);
        $this->actingAsTenant($a);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-22T18:00'])->assertRedirect();

        $departure = TripDeparture::acrossOrganizations()->sole();
        $this->assertSame('2024-10-22 10:00:00', $departure->booking_deadline_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_ac_22_without_a_meeting_time_same_day_is_refused_and_custom_may_reach_the_end_of_the_start_date(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => null]);
        $this->actingAsTenant($a);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'same_day'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_deadline_preset' => __('trips.errors.deadline_same_day_unavailable')]);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'h1'])->assertRedirect();
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-24 23:59'])->assertRedirect();
        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-25 00:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_deadline_at' => __('trips.errors.deadline_too_late', ['limit' => '24 Okt 23.59'])]);
    }

    public function test_ac_22_adding_a_meeting_time_later_does_not_change_existing_deadlines(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => null]);
        $departure = $this->departureFor($a, $trip, '2024-10-24');
        $before = $departure->booking_deadline_at;

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['meeting_time' => '06:00'])->assertOk();

        $this->assertTrue($this->freshDeparture($departure)->booking_deadline_at->equalTo($before));

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['booking_deadline_preset' => 'same_day'])->assertRedirect();
        $this->assertSame('2024-10-24 05:00', $this->freshDeparture($departure)->booking_deadline_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_ac_23_a_deadline_already_in_the_past_is_rejected(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->actingAsTenant($a);

        // 12 Okt with H-3 closes 9 Okt — already past on 10 Okt.
        $this->store($trip, ['start_date' => '2024-10-12', 'capacity' => 16, 'booking_deadline_preset' => 'h3'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_deadline_preset' => 'Tutup pesan H-3 sudah lewat untuk tanggal ini. Pilih yang lain.']);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-10 09:59'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_deadline_at' => __('trips.errors.deadline_custom_past')]);

        $this->assertDatabaseCount('trip_departures', 0);
    }

    public function test_ac_23_a_deadline_after_the_meeting_time_is_rejected(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => '06:00']);
        $this->actingAsTenant($a);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-24 06:01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_deadline_at' => __('trips.errors.deadline_too_late', ['limit' => '24 Okt 06.00'])]);

        $this->store($trip, ['start_date' => '2024-10-24', 'capacity' => 16, 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-24 06:00'])->assertRedirect();
    }

    public function test_ac_23_a_departure_today_with_same_day_after_meeting_time_minus_one_hour_is_rejected(): void
    {
        [$a] = $this->createTenants();
        // Now 10:00; kumpul 10:30 → closes 09:30, already past.
        $trip = $this->tripFor($a, ['meeting_time' => '10:30']);
        $this->actingAsTenant($a);

        $this->store($trip, ['start_date' => '2024-10-10', 'capacity' => 16, 'booking_deadline_preset' => 'same_day'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_deadline_preset');
    }

    public function test_ac_24_relative_presets_follow_a_new_start_date(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['start_date' => '2024-10-31'])->assertRedirect();

        $fresh = $this->freshDeparture($departure);
        $this->assertSame('2024-11-02', $fresh->end_date->toDateString());
        $this->assertSame('2024-10-28 23:59', $fresh->booking_deadline_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_ac_24_same_day_deadlines_follow_a_new_meeting_time(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => '06:00']);
        $departure = $this->departureFor($a, $trip, '2024-10-24', ['booking_deadline_preset' => 'same_day', 'booking_deadline_at' => '2024-10-23 22:00:00']);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['meeting_time' => '08:00'])->assertOk();

        $this->assertSame('2024-10-24 07:00', $this->freshDeparture($departure)->booking_deadline_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_ac_24_custom_deadlines_are_not_moved_but_re_validated(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => '06:00']);
        $departure = $this->departureFor($a, $trip, '2024-10-24', ['booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-21 20:00:00']);

        // 22 Okt 03.00 WIB. Moving the date keeps the custom deadline (still before the new meeting time).
        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['start_date' => '2024-10-30'])->assertRedirect();
        $this->assertSame('2024-10-21 20:00', $this->freshDeparture($departure)->booking_deadline_at->utc()->format('Y-m-d H:i'));

        // A date before the custom deadline is refused.
        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['start_date' => '2024-10-21'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_deadline_at');

        // A meeting time earlier than the custom deadline is refused on the trip.
        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['start_date' => '2024-10-22'])
            ->assertRedirect();
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['meeting_time' => '02:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['meeting_time' => __('trips.errors.meeting_time_custom', ['n' => 1])]);
    }

    public function test_ac_24_removing_the_meeting_time_is_refused_while_a_departure_uses_same_day(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['meeting_time' => '06:00']);
        $this->departureFor($a, $trip, '2024-10-24', ['booking_deadline_preset' => 'same_day', 'booking_deadline_at' => '2024-10-23 22:00:00']);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['meeting_time' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['meeting_time' => __('trips.errors.meeting_time_same_day', ['n' => 1])]);
    }

    public function test_ac_25_copy_previous_uses_the_latest_departure_and_replaces_a_custom_deadline(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-24', ['capacity' => 16, 'price_override' => null, 'booking_deadline_preset' => 'h1']);
        $this->departureFor($a, $trip, '2024-11-14', ['capacity' => 12, 'price_override' => 1_150_000, 'booking_deadline_preset' => 'custom']);

        $this->actingAsTenant($a)->getJson(route('trips.departures.copy', $trip))
            ->assertOk()
            ->assertExactJson(['capacity' => 12, 'price_override' => 1_150_000, 'booking_deadline_preset' => 'h3']);
    }

    public function test_ac_25_copy_is_unavailable_without_departures(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->getJson(route('trips.departures.copy', $trip))->assertNotFound();
        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('copyTemplate', null));
    }

    public function test_ac_26_upcoming_are_sorted_ascending_and_past_ones_grouped(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-11-14');
        $this->departureFor($a, $trip, '2024-10-24', ['price_override' => 1_150_000], seatsTaken: 14);
        $this->travelTo(now()->subMonth());
        $this->departureFor($a, $trip, '2024-09-20');
        $this->freezeClock();

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.upcoming.0.start_date', '2024-10-24')
                ->where('departures.upcoming.0.end_date', '2024-10-26')
                ->where('departures.upcoming.0.seats_taken', 14)
                ->where('departures.upcoming.0.capacity', 16)
                ->where('departures.upcoming.0.effective_price', 1_150_000)
                ->where('departures.upcoming.0.booking_deadline_preset', 'h3')
                ->where('departures.upcoming.1.start_date', '2024-11-14')
                ->where('departures.upcoming.1.effective_price', 1_250_000)
                ->has('departures.past', 1)
                ->where('departures.past.0.display_status', 'past')
                ->where('departures.past.0.can_delete', false));
    }

    public function test_ac_26_past_departures_are_read_only(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->travelTo(now()->subMonth());
        $past = $this->departureFor($a, $trip, '2024-09-20');
        $this->freezeClock();

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $past]), ['capacity' => 20])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departure' => __('trips.errors.departure_past_read_only')]);
        $this->actingAsTenant($a)->deleteJson(route('trips.departures.destroy', [$trip, $past]))->assertUnprocessable();
    }

    public function test_ac_27_status_is_open_closed_full_or_past(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-24');
        $this->departureFor($a, $trip, '2024-10-25', ['status' => 'closed', 'closed_reason' => 'manual']);
        $this->departureFor($a, $trip, '2024-10-26', seatsTaken: 16);
        // Open but its deadline passed before the job ran: shown as closed.
        $this->departureFor($a, $trip, '2024-10-11', ['booking_deadline_at' => '2024-10-10 01:00:00']);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.upcoming.0.start_date', '2024-10-11')
                ->where('departures.upcoming.0.display_status', 'closed')
                ->where('departures.upcoming.1.display_status', 'open')
                ->where('departures.upcoming.2.display_status', 'closed')
                ->where('departures.upcoming.3.display_status', 'full'));
    }

    public function test_ac_28_the_start_date_cannot_change_once_seats_are_taken(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 2);

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['start_date' => '2024-10-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => 'Sudah ada pesanan. Tutup keberangkatan ini dan buat tanggal baru.']);

        $this->assertSame('2024-10-24', $this->freshDeparture($departure)->start_date->toDateString());

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('departures.upcoming.0.can_change_date', false));
    }

    public function test_ac_29_only_departures_without_bookings_can_be_deleted(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $empty = $this->departureFor($a, $trip, '2024-10-24');
        $booked = $this->departureFor($a, $trip, '2024-10-31', seatsTaken: 1);

        $this->actingAsTenant($a)->deleteJson(route('trips.departures.destroy', [$trip, $booked]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departure' => __('trips.errors.departure_has_bookings')]);
        $this->actingAsTenant($a)->deleteJson(route('trips.departures.destroy', [$trip, $empty]))->assertRedirect();

        $this->assertDatabaseMissing('trip_departures', ['id' => $empty->id]);
        $this->assertDatabaseHas('trip_departures', ['id' => $booked->id]);
    }

    public function test_ac_29_a_departure_that_ever_had_a_booking_cannot_be_deleted(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24');
        $this->bindBookings(hasAny: true, active: 0);

        $this->actingAsTenant($a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('departures.upcoming.0.can_delete', false));
        $this->actingAsTenant($a)->deleteJson(route('trips.departures.destroy', [$trip, $departure]))->assertUnprocessable();
    }

    public function test_ac_30_closing_the_last_open_departure_keeps_the_trip_published_with_a_notice(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->put(route('trips.departures.update', [$trip, $departure]), ['status' => 'closed'])
            ->assertRedirect()
            ->assertSessionHas('notice', 'Trip tetap tampil, tapi belum ada tanggal yang bisa dipesan.');

        $this->assertSame('published', $this->freshTrip($trip)->status->value);
        $this->assertSame('manual', $this->freshDeparture($departure)->closed_reason->value);
    }

    public function test_ac_30_deleting_the_last_open_departure_shows_the_notice(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->delete(route('trips.departures.destroy', [$trip, $departure]))
            ->assertSessionHas('notice', __('trips.notices.last_departure'));
        $this->assertSame('published', $this->freshTrip($trip)->status->value);
    }

    public function test_ac_30_no_notice_while_another_departure_is_bookable(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);
        $departure = $this->departureFor($a, $trip, '2024-10-24');
        $this->departureFor($a, $trip, '2024-10-31');

        $this->actingAsTenant($a)->put(route('trips.departures.update', [$trip, $departure]), ['status' => 'closed'])
            ->assertSessionMissing('notice');
    }

    public function test_duration_change_moves_end_dates_only_for_departures_without_bookings(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $free = $this->departureFor($a, $trip, '2024-10-24');
        $booked = $this->departureFor($a, $trip, '2024-10-31', seatsTaken: 3);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['duration_label' => 'days', 'duration_days' => 4, 'duration_nights' => 3])
            ->assertOk()
            ->assertJsonPath('notices.0', '1 keberangkatan yang sudah ada pesanan tidak berubah.');

        $this->assertSame('2024-10-27', $this->freshDeparture($free)->end_date->toDateString());
        $this->assertSame('2024-11-02', $this->freshDeparture($booked)->end_date->toDateString());
    }

    public function test_booking_mode_cannot_change_while_a_future_departure_has_seats_taken(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 1);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['booking_mode' => 'request_date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_mode' => __('trips.errors.booking_mode_locked')]);
    }

    public function test_a_departure_from_another_trip_of_the_same_seller_is_404(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $other = $this->tripFor($a);
        $departure = $this->departureFor($a, $other, '2024-10-24');

        $this->actingAsTenant($a)->putJson(route('trips.departures.update', [$trip, $departure]), ['capacity' => 20])->assertNotFound();
    }

    private function bindBookings(bool $hasAny, int $active): void
    {
        $this->app->instance(DepartureBookings::class, new class($hasAny, $active) implements DepartureBookings
        {
            public function __construct(private bool $hasAny, private int $active) {}

            public function hasAnyBooking(TripDeparture $departure): bool
            {
                return $this->hasAny;
            }

            public function activeBookingCount(TripDeparture $departure): int
            {
                return $this->active;
            }
        });
    }
}
