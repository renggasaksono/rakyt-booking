<?php

namespace Tests\Feature\Trips;

use App\Models\AuditEvent;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-1–AC-6, AC-8 (server side), AC-14.
 */
class TripEditingTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    public function test_ac_1_open_trip_preset_is_scheduled_three_days_two_nights(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['type' => 'open_trip', 'name' => 'Karimunjawa 3H2M'])
            ->assertCreated()
            ->assertJsonPath('trip.booking_mode', 'scheduled')
            ->assertJsonPath('trip.duration_days', 3)
            ->assertJsonPath('trip.duration_nights', 2)
            ->assertJsonPath('trip.status', 'draft');
    }

    public function test_ac_1_day_trip_is_no_longer_a_valid_type(): void
    {
        [$a] = $this->createTenants();

        // Day Trip dropped 2026-10-22 (two-type enum): the picker and Rule::enum reject it.
        $this->actingAsTenant($a)->postJson(route('trips.store'), ['type' => 'day_trip', 'name' => 'Snorkeling Menjangan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_ac_1_private_preset_is_request_date_without_duration(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['type' => 'private', 'name' => 'Private Bromo'])
            ->assertCreated()
            ->assertJsonPath('trip.booking_mode', 'request_date')
            ->assertJsonPath('trip.duration_label', null)
            ->assertJsonPath('trip.duration_days', null);
    }

    public function test_ac_1_prefilled_values_stay_editable(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), [
            'type' => 'open_trip',
            'name' => 'Open trip pendek',
            'booking_mode' => 'request_date',
            'duration_label' => 'days',
            'duration_days' => 2,
            'duration_nights' => 1,
        ])->assertCreated()
            ->assertJsonPath('trip.booking_mode', 'request_date')
            ->assertJsonPath('trip.duration_days', 2);
    }

    public function test_ac_1_the_editor_offers_the_type_presets(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('trips.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Trips/Edit')
                ->where('trip', null)
                ->where('options.types.0', ['value' => 'open_trip', 'booking_mode' => 'scheduled', 'duration' => ['duration_label' => 'days', 'duration_days' => 3, 'duration_nights' => 2]])
                ->where('options.types.1', ['value' => 'private', 'booking_mode' => 'request_date', 'duration' => null])
                ->where('options.types', fn ($types) => count($types) === 2));
    }

    public function test_ac_2_a_name_alone_saves_a_draft(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Karimunjawa'])->assertCreated();

        $trip = Trip::acrossOrganizations()->sole();
        $this->assertSame($a->id(), $trip->organization_id);
        $this->assertSame('draft', $trip->status->value);
        $this->assertSame('karimunjawa', $trip->slug);
        $this->assertSame(26, strlen($trip->public_id));
    }

    public function test_ac_2_without_a_name_nothing_is_saved(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => '  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => __('trips.errors.name_required')]);

        $this->assertDatabaseCount('trips', 0);
    }

    public function test_ac_2_name_must_be_3_to_80_characters(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => __('trips.errors.name_min', ['min' => 3])]);
        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => str_repeat('a', 81)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('trips', 0);

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Abc'])->assertCreated();
        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => str_repeat('a', 80)])->assertCreated();
        $this->assertDatabaseCount('trips', 2);
    }

    public function test_ac_2_slugs_are_unique_per_organization_and_never_change(): void
    {
        [$a, $b] = $this->createTenants();

        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Karimunjawa'])->assertCreated();
        $this->actingAsTenant($a)->postJson(route('trips.store'), ['name' => 'Karimunjawa'])->assertCreated();
        $this->actingAsTenant($b)->postJson(route('trips.store'), ['name' => 'Karimunjawa'])->assertCreated();

        $this->assertSame(['karimunjawa', 'karimunjawa-2'], Trip::acrossOrganizations()->where('organization_id', $a->id())->orderBy('id')->pluck('slug')->all());
        $this->assertSame(['karimunjawa'], Trip::acrossOrganizations()->where('organization_id', $b->id())->pluck('slug')->all());

        $trip = Trip::acrossOrganizations()->where('organization_id', $a->id())->orderBy('id')->first();
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['name' => 'Karimunjawa Baru'])->assertOk();
        $this->assertSame('karimunjawa', $this->freshTrip($trip)->slug);
    }

    public function test_ac_3_autosave_saves_only_the_changed_fields_and_returns_the_time(): void
    {
        $this->freezeClock('2024-10-10 10:42');
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['short_description' => 'Lama']);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['meeting_point' => 'Pelabuhan Kartini'])
            ->assertOk()
            ->assertJsonPath('saved_at', '10.42')
            ->assertJsonPath('trip.meeting_point', 'Pelabuhan Kartini')
            ->assertJsonPath('trip.short_description', 'Lama')
            ->assertJsonStructure(['checklist' => ['items', 'done', 'total'], 'preview']);
    }

    public function test_ac_3_autosave_reports_time_in_the_organization_zone(): void
    {
        $this->freezeClock('2024-10-10 10:42');
        [$a] = $this->createTenants();
        $a->organization->update(['timezone' => 'Asia/Makassar']);
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['name' => 'Labuan Bajo'])
            ->assertOk()
            ->assertJsonPath('saved_at', '11.42');
    }

    public function test_ac_3_invalid_autosave_changes_nothing(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['name' => 'Asli']);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['name' => '', 'base_price' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'base_price']);

        $this->assertSame('Asli', $this->freshTrip($trip)->name);
    }

    public function test_ac_4_published_trips_are_not_autosaved(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['name' => 'Tampil'], published: true);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['name' => 'Diam-diam'])
            ->assertStatus(409)
            ->assertJsonPath('message', __('trips.errors.published_autosave'));

        $this->assertSame('Tampil', $this->freshTrip($trip)->name);
    }

    public function test_ac_4_simpan_perubahan_saves_a_published_trip(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['name' => 'Tampil'], published: true);

        $this->actingAsTenant($a)->from(route('trips.edit', $trip))
            ->put(route('trips.update', $trip), ['name' => 'Tampil Baru', 'base_price' => 1_300_000])
            ->assertRedirect(route('trips.edit', $trip))
            ->assertSessionHas('toast', __('trips.notices.saved'));

        $fresh = $this->freshTrip($trip);
        $this->assertSame('Tampil Baru', $fresh->name);
        $this->assertSame('published', $fresh->status->value);
    }

    public function test_ac_4_a_published_trip_cannot_lose_its_base_price(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);

        $this->actingAsTenant($a)->putJson(route('trips.update', $trip), ['name' => $trip->name, 'base_price' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_price' => __('trips.errors.published_needs_price')]);
    }

    public function test_ac_5_custom_duration_allows_up_to_thirty_days_and_nights_up_to_days(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['duration_label' => 'days', 'duration_days' => 5, 'duration_nights' => 4])->assertOk();
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['duration_label' => 'days', 'duration_days' => 30, 'duration_nights' => 30])->assertOk();

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['duration_label' => 'days', 'duration_days' => 3, 'duration_nights' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_nights' => __('trips.duration.nights_max')]);
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['duration_label' => 'days', 'duration_days' => 31, 'duration_nights' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_days');

        $this->assertSame([30, 30], [$this->freshTrip($trip)->duration_days, $this->freshTrip($trip)->duration_nights]);
    }

    public function test_ac_5_beberapa_jam_is_stored_as_one_day_zero_nights(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['duration_label' => 'hours', 'duration_days' => 9, 'duration_nights' => 9])
            ->assertOk()
            ->assertJsonPath('trip.duration_display', 'Beberapa jam');

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'duration_label' => 'hours', 'duration_days' => 1, 'duration_nights' => 0]);
    }

    public function test_ac_5_the_picker_offers_the_presets(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('trips.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.durations', fn ($durations) => collect($durations)->pluck('display')->all() === ['Beberapa jam', '1 hari', '2 hari 1 malam', '3 hari 2 malam', '4 hari 3 malam']));
    }

    public function test_ac_6_inclusions_are_trimmed_deduplicated_and_blanks_dropped(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), [
            'inclusions' => ['  Kapal ', 'Homestay', '', null, 'Kapal', 'Makan   6x'],
            'exclusions' => ['Tiket pesawat', '   '],
        ])->assertOk()
            ->assertJsonPath('trip.inclusions', ['Kapal', 'Homestay', 'Makan 6x'])
            ->assertJsonPath('trip.exclusions', ['Tiket pesawat']);
    }

    public function test_ac_6_items_are_at_most_forty_characters_and_twenty_items(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['inclusions' => [str_repeat('a', 40)]])->assertOk();

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['inclusions' => [str_repeat('a', 41)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inclusions.0' => __('trips.errors.item_too_long', ['max' => 40])]);

        $items = array_map(fn ($n) => "Item {$n}", range(1, 21));
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['exclusions' => $items])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exclusions' => __('trips.errors.items_max', ['max' => 20])]);
    }

    public function test_ac_6_suggestion_chips_come_from_one_lang_list(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('trips.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.inclusion_suggestions', ['Kapal', 'Homestay', 'Makan', 'Alat snorkel', 'Guide'])
                ->where('options.limits.list_item', 40)
                ->where('options.limits.list_items', 20));
    }

    public function test_ac_8_prices_are_integer_rupiah_between_one_thousand_and_one_hundred_million(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        foreach ([999, 100_000_001, '1.250.000', 12.5] as $bad) {
            $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['base_price' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('base_price');
        }

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['base_price' => 1000])->assertOk();
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['base_price' => 100_000_000])->assertOk();
        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), ['base_price' => 1_250_000])->assertOk();

        $this->assertSame(1_250_000, $this->freshTrip($trip)->base_price);
    }

    public function test_other_fields_are_validated(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), [
            'name' => str_repeat('n', 81),
            'short_description' => str_repeat('d', 301),
            'meeting_point' => str_repeat('m', 201),
            'meeting_time' => '25:00',
            'map_url' => 'javascript:alert(1)',
            'itinerary' => [str_repeat('i', 301)],
            'terms' => str_repeat('t', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'short_description', 'meeting_point', 'meeting_time', 'map_url', 'itinerary.0', 'terms']);

        $this->actingAsTenant($a)->patchJson(route('trips.autosave', $trip), [
            'meeting_time' => '06:30',
            'map_url' => 'https://maps.app.goo.gl/abc',
            'itinerary' => ['Kumpul di pelabuhan', '', 'Pulang', '', ''],
            'terms' => 'DP 50%',
        ])->assertOk()
            ->assertJsonPath('trip.meeting_time', '06:30')
            ->assertJsonPath('trip.itinerary', ['Kumpul di pelabuhan', '', 'Pulang']);
    }

    public function test_ac_14_publishing_never_reads_a_plan_limit(): void
    {
        [$a] = $this->createTenants();
        $this->withPaymentMethod();
        $this->freezeClock();

        foreach (['Satu', 'Dua', 'Tiga'] as $name) {
            $trip = $this->tripFor($a, ['name' => $name]);
            $this->departureFor($a, $trip);
            $this->asTenant($a, fn () => $trip->photos()->create(['path' => 'org/x/trips/y/foto.jpg', 'position' => 1]));

            $this->actingAsTenant($a)->postJson(route('trips.publish', $trip))->assertRedirect();
        }

        $this->assertSame(3, Trip::acrossOrganizations()->where('status', 'published')->count());
        $this->assertSame(3, AuditEvent::acrossOrganizations()->where('action', 'trip.published')->count());
    }
}
