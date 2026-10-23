<?php

namespace Tests\Feature\Bookings;

use App\Models\Trip;
use App\Trips\TripStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-005 section A: the booking form (mockup 1c), its 404s, the "no longer
 * bookable" state, and its props and headers (AC-36, AC-37). The clock is
 * Thursday 2024-10-10 10:00 WIB.
 */
class BookingFormTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->trip = $this->bookableTrip($this->a);
    }

    public function test_ac_1_the_form_renders_for_a_bookable_departure(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');

        $props = $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id);

        $this->assertSame('Karimunjawa 3H2M', $props['trip']['name']);
        $this->assertSame(['start_date' => '2024-10-24', 'end_date' => '2024-10-26'], array_intersect_key($props['departure'], array_flip(['start_date', 'end_date'])));
        $this->assertSame(['point' => 'Pelabuhan Jepara', 'time' => '06:00'], $props['meeting']);
        $this->assertSame('2024-10-10', $props['context']['today']);
        $this->assertSame(url('/kencana/karimunjawa-3h2m'), $props['urls']['trip']);
        $this->assertSame('Langkah 2 dari 3', $props['copy']['form']['kicker']);
        $this->assertSame('Data pemesan', $props['copy']['form']['title']);
        $this->assertSame('Kam', $props['calendar']['days_short'][4]);
    }

    public function test_ac_2_unknown_or_foreign_ids_are_the_same_404_for_get_and_post(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
        $other = $this->tripFor($this->a, ['name' => 'Bromo'], published: true);
        $otherDeparture = $this->departureFor($this->a, $other, '2024-10-24');
        $draft = $this->tripFor($this->a, ['name' => 'Draf']);
        $draftDeparture = $this->departureFor($this->a, $draft, '2024-10-24');
        $request = $this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private']));
        $b = $this->seller('bahari', 'Bahari');
        $foreign = $this->departureFor($b, $this->bookableTrip($b), '2024-10-24');

        $cases = [
            ['nope', 'karimunjawa-3h2m', $departure->public_id],        // unknown slug
            ['daftar', 'karimunjawa-3h2m', $departure->public_id],      // reserved slug
            ['ke--ncana', 'karimunjawa-3h2m', $departure->public_id],   // malformed slug
            ['kencana', 'draf', $draftDeparture->public_id],            // draft trip
            ['kencana', 'private', $departure->public_id],              // request-date trip
            ['kencana', 'karimunjawa-3h2m', null],                      // missing departure
            ['kencana', 'karimunjawa-3h2m', 'abc'],                     // malformed departure
            ['kencana', 'karimunjawa-3h2m', Str::lower((string) Str::ulid())], // unknown departure
            ['kencana', 'karimunjawa-3h2m', $otherDeparture->public_id], // another trip's departure
            ['kencana', 'karimunjawa-3h2m', $foreign->public_id],       // another seller's departure
        ];

        $this->assertNotNull($request);

        foreach ($cases as [$slug, $trip, $id]) {
            $this->get($this->formUrl($slug, $trip, $id))->assertNotFound()->assertSee('Halaman tidak ditemukan');
            // No phone: only the IP limit applies (AC-39), so ten probes are not throttled.
            $this->submitBooking($slug, $trip, ['keberangkatan' => $id, 'phone' => ''] + $this->bookingInput($departure))->assertNotFound();
        }

        $this->assertSame(0, $this->bookingCount());
    }

    public function test_ac_2_an_unpublished_trip_is_a_404(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
        $this->asTenant($this->a, fn () => $this->trip->forceFill(['status' => TripStatus::Draft])->save());

        $this->get($this->formUrl('kencana', 'karimunjawa-3h2m', $departure->public_id))->assertNotFound();
    }

    public function test_ac_3_a_departure_no_longer_bookable_shows_the_state_without_a_form(): void
    {
        $full = $this->departureFor($this->a, $this->trip, '2024-10-24', ['capacity' => 2]);
        $this->bookingFor($this->a, $full, 2);
        $closed = $this->departureFor($this->a, $this->trip, '2024-10-25', ['status' => 'closed', 'closed_reason' => 'manual']);
        $pastDeadline = $this->departureFor($this->a, $this->trip, '2024-10-11');
        $started = $this->departureFor($this->a, $this->trip, '2024-10-09', ['booking_deadline_at' => now()->addDay()]);

        $this->assertSame('full', $this->formProps('kencana', 'karimunjawa-3h2m', $full->public_id)['departure']['state']);

        foreach ([$closed, $pastDeadline, $started] as $departure) {
            $props = $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id);

            $this->assertSame('closed', $props['departure']['state']);
            $this->assertNull($props['departure']['seats_left']);
            $this->assertNull($props['departure']['price']);
        }

        $copy = $this->formProps('kencana', 'karimunjawa-3h2m', $full->public_id)['copy']['form'];
        $this->assertSame('Tanggal ini sudah tidak bisa dipesan.', $copy['unavailable']);
        $this->assertSame(['full' => 'Penuh', 'closed' => 'Ditutup'], $copy['reasons']);
        $this->assertSame('Pilih tanggal lain →', $copy['other_date']);
    }

    public function test_ac_4_to_ac_8_fields_stepper_total_hold_and_privacy_come_from_the_server(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24', ['capacity' => 16]);
        $this->bookingFor($this->a, $departure, 4);

        $props = $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id);
        $copy = $props['copy']['form'];

        // AC-5: the stepper's maximum; AC-6: the unit price for the live total.
        $this->assertSame(12, $props['departure']['seats_left']);
        $this->assertSame(1_250_000, $props['departure']['price']);
        $this->assertSame('Sisa kuota :n', $copy['participants_help']);
        $this->assertSame('Konfirmasi & info keberangkatan dikirim ke sini.', $copy['phone_help']);
        $this->assertSame(['Nama lengkap', 'Nomor WhatsApp', 'Jumlah peserta', 'Catatan (opsional)'], [$copy['name'], $copy['phone'], $copy['participants'], $copy['notes']]);

        // AC-7: the hours come from the server's constant.
        $this->assertSame(120, $props['hold_minutes']);
        $this->assertSame(config('rakyt.booking.hold_minutes'), $props['hold_minutes']);
        $this->assertSame('Kuota ditahan :hours jam sampai dibayar, lalu dilepas.', $copy['hold']);

        // AC-8
        $this->assertSame('Data Anda hanya diteruskan ke :name untuk mengurus pesanan ini.', $copy['privacy']);
        $this->assertSame(route('legal.privacy'), $props['urls']['privacy']);

        // AC-9
        $this->assertSame(['Kirim pesanan', 'Mengirim…', 'Belum terkirim. Periksa koneksi lalu coba lagi.'], [$copy['submit'], $copy['sending'], $copy['network_error']]);
    }

    public function test_ac_4_the_page_renders_the_fields_with_their_attributes(): void
    {
        // The Vue template is the source of the field order and attributes; check it as text.
        $template = file_get_contents(resource_path('js/pages/Public/BookingForm.vue'));

        $positions = array_map(fn (string $id) => strpos($template, 'id="'.$id.'"'), ['name', 'phone', 'participants', 'notes']);
        $sorted = $positions;
        sort($sorted);

        $this->assertNotContains(false, $positions);
        $this->assertSame($sorted, $positions, 'Fields are in the order Nama, WhatsApp, peserta, catatan.');
        $this->assertStringContainsString('autocomplete="name"', $template);
        $this->assertStringContainsString('inputmode="tel"', $template);
        $this->assertStringContainsString('autocomplete="tel-national"', $template);
        $this->assertDoesNotMatchRegularExpression('/placeholder=/', $template, 'Labels are visible text, not placeholders.');
        // One gold button per screen (AC-42): "Kirim pesanan", or "Pilih tanggal lain →" in its v-else.
        $this->assertSame(2, preg_match_all('/(?<![\w:-])bg-prada(?![\w\/-])/', $template));
        $this->assertMatchesRegularExpression('/v-else\s+:href="urls.trip"\s+class="[^"]*bg-prada/', $template);
    }

    public function test_ac_16_each_form_load_gets_a_new_submission_key(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');

        $first = $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id)['submission_key'];
        $second = $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id)['submission_key'];

        $this->assertTrue(Str::isUuid($first));
        $this->assertNotSame($first, $second);
    }

    public function test_ac_36_the_form_is_never_stored_and_has_no_open_graph_tags(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');

        $response = $this->get($this->formUrl('kencana', 'karimunjawa-3h2m', $departure->public_id))->assertOk();

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $response->assertSee('<title>Pesan Karimunjawa 3H2M · Kencana Trip</title>', false);
        $response->assertDontSee('og:', false);
    }

    public function test_ac_37_the_form_props_are_exactly_the_public_fields(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');

        $props = $this->formProps('kencana', 'karimunjawa-3h2m', $departure->public_id);

        $this->assertEqualsCanonicalizing(
            ['title', 'organization', 'trip', 'departure', 'meeting', 'submission_key', 'hold_minutes', 'context', 'copy', 'calendar', 'urls', 'errors'],
            array_keys($props),
        );
        $this->assertSame(['name', 'slug'], array_keys($props['organization']));
        $this->assertSame(['slug', 'name'], array_keys($props['trip']));
        $this->assertSame(['public_id', 'start_date', 'end_date', 'state', 'seats_left', 'price'], array_keys($props['departure']));
        $this->assertSame(['point', 'time'], array_keys($props['meeting']));
        $this->assertSame(['today'], array_keys($props['context']));
        $this->assertSame(['trip', 'store', 'privacy', 'home'], array_keys($props['urls']));

        $json = json_encode($props);
        $this->assertStringNotContainsString('"id"', $json);
        $this->assertStringNotContainsString('organization_id', $json);
        $this->assertStringNotContainsString('seats_taken', $json);
        $this->assertStringNotContainsString('+6281277881020', $json, 'The seller WhatsApp number is not on the form.');
    }

    public function test_seller_shared_props_never_reach_the_form(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');

        $props = $this->propsOf($this->actingAsTenant($this->a)->get($this->formUrl('kencana', 'karimunjawa-3h2m', $departure->public_id)), 'Public/BookingForm');

        $this->assertArrayNotHasKey('seller', $props);
        $this->assertArrayNotHasKey('account', $props);
    }

    public function test_uppercase_slugs_redirect_to_the_canonical_form_url(): void
    {
        $departure = $this->departureFor($this->a, $this->trip, '2024-10-24');

        $this->get('/Kencana/Karimunjawa-3H2M/pesan?keberangkatan='.$departure->public_id)
            ->assertMovedPermanently()
            ->assertRedirect('/kencana/karimunjawa-3h2m/pesan?keberangkatan='.$departure->public_id);
    }
}
