<?php

namespace Tests\Feature\PublicPages;

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripPhoto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-002 section C: the trip page (mockups 1b, 2c, 3b), its head tags (AC-29,
 * AC-31) and images (AC-32). The clock is Thursday 2024-10-10 10:00 WIB.
 */
class TripPageTest extends TestCase
{
    use BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
    }

    public function test_ac_17_the_top_shows_gallery_kicker_name_and_description(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M', 'short_description' => 'Snorkeling dan camping.'], published: true);
        $second = $this->photoFor($this->a, $trip, 2);
        $cover = $this->photoFor($this->a, $trip, 1);

        $props = $this->tripProps('kencana', 'karimunjawa-3h2m');

        $this->assertSame([$cover->url(400), $second->url(400)], array_column($props['trip']['photos'], 'src'));
        $this->assertSame(['Open trip', '3 hari 2 malam'], [$props['trip']['type_label'], $props['trip']['duration']]);
        $this->assertSame('Karimunjawa 3H2M', $props['trip']['name']);
        $this->assertSame('Snorkeling dan camping.', $props['trip']['short_description']);
        $this->assertSame(route('public.seller', ['organization' => 'kencana']), $props['urls']['seller']);

        // A one-day Open trip (Day Trip dropped 2026-10-22): "Beberapa jam" duration.
        $this->tripFor($this->a, ['name' => 'Jepara Kota', 'type' => 'open_trip', 'duration_label' => 'hours', 'duration_days' => 1, 'duration_nights' => 0], published: true);
        $this->assertSame(['Open trip', 'Beberapa jam'], array_values(array_intersect_key($this->tripProps('kencana', 'jepara-kota')['trip'], array_flip(['type_label', 'duration']))));

        $this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private', 'duration_label' => null, 'duration_days' => null, 'duration_nights' => null]));
        $private = $this->tripProps('kencana', 'private')['trip'];
        $this->assertSame(['Private', null], [$private['type_label'], $private['duration']]);
    }

    public function test_ac_18_departures_from_today_in_date_then_creation_order(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa', 'meeting_time' => '18:00'], published: true);
        $this->departureFor($this->a, $trip, '2024-10-09');                     // started: hidden
        $later = $this->departureFor($this->a, $trip, '2024-10-24');
        $sameDayFirst = $this->departureFor($this->a, $trip, '2024-10-17');
        $sameDaySecond = $this->departureFor($this->a, $trip, '2024-10-17', ['status' => 'closed', 'closed_reason' => 'manual']);
        // Today, with a same-day deadline still open (1 hour before the 18.00 meeting).
        $today = $this->departureFor($this->a, $trip, '2024-10-10', [
            'end_date' => '2024-10-10',
            'booking_deadline_preset' => 'same_day',
            'booking_deadline_at' => CarbonImmutable::parse('2024-10-10 17:00', 'Asia/Jakarta')->utc(),
        ]);

        $props = $this->tripProps('kencana', 'karimunjawa');

        $this->assertSame(
            [$today->public_id, $sameDayFirst->public_id, $sameDaySecond->public_id, $later->public_id],
            array_column($props['departures'], 'public_id'),
        );
        $this->assertSame('bookable', $props['departures'][0]['state']);
        $this->assertSame(['low_seats' => 5, 'urgent_days' => 7, 'shown' => 10], $props['rules']);
        $this->assertSame(['today' => '2024-10-10', 'now_local' => '2024-10-10 10:00'], $props['context']);
    }

    public function test_ac_18_more_than_ten_departures_are_all_sent_for_the_reveal(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);

        foreach (range(0, 11) as $week) {
            $this->departureFor($this->a, $trip, CarbonImmutable::parse('2024-10-24')->addWeeks($week)->toDateString());
        }

        $this->assertCount(12, $this->tripProps('kencana', 'karimunjawa')['departures']);
    }

    public function test_ac_19_bookable_rows_carry_dates_seats_price_and_deadline(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa', 'base_price' => 1_250_000], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24', seatsTaken: 13);
        $this->departureFor($this->a, $trip, '2024-10-31', ['price_override' => 1_150_000, 'end_date' => '2024-11-02']);

        [$first, $second] = $this->tripProps('kencana', 'karimunjawa')['departures'];

        $this->assertSame(['2024-10-24', '2024-10-26', 'bookable', 3, 16, 1_250_000, '2024-10-21 23:59', 'h3'], [
            $first['start_date'], $first['end_date'], $first['state'], $first['seats_left'], $first['capacity'], $first['price'], $first['deadline'], $first['deadline_preset'],
        ]);
        $this->assertSame([1_150_000, '2024-11-02', '2024-10-28 23:59'], [$second['price'], $second['end_date'], $second['deadline']]);
    }

    public function test_ac_19a_deadlines_are_local_wall_clock_values_with_their_preset(): void
    {
        $this->a->organization->update(['timezone' => 'Asia/Jayapura']);
        $trip = $this->tripFor($this->a, ['name' => 'Raja Ampat'], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24', [
            'booking_deadline_preset' => 'custom',
            'booking_deadline_at' => CarbonImmutable::parse('2024-10-20 18:30', 'Asia/Jayapura')->utc(),
        ]);

        $row = $this->tripProps('kencana', 'raja-ampat')['departures'][0];
        $this->assertSame(['2024-10-20 18:30', 'custom'], [$row['deadline'], $row['deadline_preset']]);
    }

    public function test_ac_20_full_and_closed_rows_show_no_price_and_cannot_be_chosen(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $this->departureFor($this->a, $trip, '2024-10-17', seatsTaken: 16);
        $this->departureFor($this->a, $trip, '2024-10-18', ['status' => 'closed', 'closed_reason' => 'manual']);
        $this->departureFor($this->a, $trip, '2024-10-19', ['status' => 'closed', 'closed_reason' => 'deadline']);
        // Deadline passed (9 Okt 23.59) before the auto-close job ran.
        $this->departureFor($this->a, $trip, '2024-10-12');

        $rows = $this->tripProps('kencana', 'karimunjawa')['departures'];

        // 12 Okt (deadline passed), 17 Okt (full), 18 Okt (closed), 19 Okt (closed by deadline).
        $this->assertSame(['closed', 'full', 'closed', 'closed'], array_column($rows, 'state'));

        foreach ($rows as $row) {
            $this->assertSame([null, null, null, null, null], [$row['price'], $row['seats_left'], $row['capacity'], $row['deadline'], $row['deadline_preset']]);
        }
    }

    public function test_ac_22_pesan_links_to_the_f005_form_by_departure_public_id(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $departure = $this->departureFor($this->a, $trip, '2024-10-24');

        $props = $this->tripProps('kencana', 'karimunjawa');

        $this->assertSame(url('/kencana/karimunjawa/pesan'), $props['urls']['book']);
        $this->assertSame($departure->public_id, $props['departures'][0]['public_id']);
    }

    public function test_ac_23_a_scheduled_trip_without_bookable_dates_offers_ajukan_tanggal(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa', 'base_price' => 1_250_000], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24', ['price_override' => 900_000], seatsTaken: 16);

        $props = $this->tripProps('kencana', 'karimunjawa');
        $this->assertSame(['full'], array_column($props['departures'], 'state'));
        $this->assertSame(1_250_000, $props['trip']['from_price']);
        // F-004 AC-6: a scheduled trip with no bookable departure hands off to WhatsApp.
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.date_request', ['name' => 'Kencana Trip', 'trip' => 'Karimunjawa'])),
            $props['urls']['request_date'],
        );

        $this->tripFor($this->a, ['name' => 'Kosong'], published: true);
        $this->assertSame([], $this->tripProps('kencana', 'kosong')['departures']);
    }

    public function test_ac_24_a_request_date_trip_has_no_departure_list(): void
    {
        $this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private Karimun', 'base_price' => 3_000_000]));

        $props = $this->tripProps('kencana', 'private-karimun');

        $this->assertSame('request_date', $props['trip']['booking_mode']);
        $this->assertSame([], $props['departures']);
        $this->assertSame(3_000_000, $props['trip']['from_price']);
        // F-004 AC-2: the request-date trip's "Ajukan tanggal →" is the WhatsApp handoff.
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode(__('whatsapp.date_request', ['name' => 'Kencana Trip', 'trip' => 'Private Karimun'])),
            $props['urls']['request_date'],
        );
    }

    public function test_ac_26_detail_sections_keep_real_day_numbers(): void
    {
        $this->tripFor($this->a, [
            'name' => 'Karimunjawa',
            'inclusions' => ['Kapal', 'Homestay'],
            'exclusions' => ['Tiket pesawat'],
            'meeting_point' => 'Pelabuhan Kartini, Jepara',
            'meeting_time' => '06:00',
            'map_url' => 'https://maps.app.goo.gl/abc',
            'itinerary' => ['Tiba dan snorkeling', '', 'Pulang'],
            'terms' => "Bayar DP 50%.\nTidak ada refund.",
        ], published: true);

        $trip = $this->tripProps('kencana', 'karimunjawa')['trip'];

        $this->assertSame(['Kapal', 'Homestay'], $trip['inclusions']);
        $this->assertSame(['Tiket pesawat'], $trip['exclusions']);
        $this->assertSame(['Pelabuhan Kartini, Jepara', '06:00', 'https://maps.app.goo.gl/abc'], [$trip['meeting_point'], $trip['meeting_time'], $trip['map_url']]);
        $this->assertSame([['day' => 1, 'text' => 'Tiba dan snorkeling'], ['day' => 3, 'text' => 'Pulang']], $trip['itinerary']);
        $this->assertSame("Bayar DP 50%.\nTidak ada refund.", $trip['terms']);

        $this->tripFor($this->a, ['name' => 'Polos', 'meeting_point' => null, 'meeting_time' => null], published: true);
        $plain = $this->tripProps('kencana', 'polos')['trip'];
        $this->assertSame([[], [], null, null, null, [], null], [$plain['inclusions'], $plain['exclusions'], $plain['meeting_point'], $plain['meeting_time'], $plain['map_url'], $plain['itinerary'], $plain['terms']]);
    }

    public function test_ac_27_seller_text_is_escaped_everywhere_and_only_http_map_links_leave(): void
    {
        $this->a->organization->update(['name' => 'Kencana <b>Trip</b>', 'bio' => '"><script>alert(2)</script>']);
        $trip = $this->tripFor($this->a, [
            'name' => '<script>alert(1)</script>',
            'short_description' => '<img src=x onerror=alert(1)>',
            'terms' => 'javascript:alert(1)',
        ], published: true);
        Trip::acrossOrganizations()->whereKey($trip->id)->update(['slug' => 'xss', 'map_url' => 'javascript:alert(1)']);

        foreach (['/kencana', '/kencana/xss'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('<script>alert', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringNotContainsString('<b>Trip</b>', $html);
        }

        $this->get('/kencana/xss')
            ->assertSee('<title>&lt;script&gt;alert(1)&lt;/script&gt; · Kencana &lt;b&gt;Trip&lt;/b&gt;</title>', false)
            ->assertSee('content="&lt;img src=x onerror=alert(1)&gt;"', false);

        $this->assertNull($this->tripProps('kencana', 'xss')['trip']['map_url']);

        foreach (['ftp://x.test/peta', 'JAVASCRIPT:alert(1)', 'data:text/html,hi'] as $bad) {
            Trip::acrossOrganizations()->whereKey($trip->id)->update(['map_url' => $bad]);
            $this->assertNull($this->tripProps('kencana', 'xss')['trip']['map_url'], $bad);
        }

        Trip::acrossOrganizations()->whereKey($trip->id)->update(['map_url' => 'HTTPS://maps.google.com/?q=Jepara']);
        $this->assertSame('HTTPS://maps.google.com/?q=Jepara', $this->tripProps('kencana', 'xss')['trip']['map_url']);
    }

    public function test_ac_28_and_ac_29_trip_head_tags_are_server_rendered(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa 3H2M Open Trip', 'base_price' => 1_250_000], published: true);
        $cover = $this->photoFor($this->a, $trip);
        $this->departureFor($this->a, $trip, '2024-10-24', ['price_override' => 1_150_000]);

        $this->get('/kencana/karimunjawa-3h2m-open-trip')
            ->assertSee('<title>Karimunjawa 3H2M Open Trip · Kencana Trip</title>', false)
            ->assertSee('<meta property="og:title" content="Karimunjawa 3H2M Open Trip">', false)
            ->assertSee('<meta property="og:description" content="Mulai Rp1.150.000/orang · 3 hari 2 malam · Kencana Trip">', false)
            ->assertSee('<meta property="og:image" content="'.$cover->url(800).'">', false)
            ->assertSee('<meta property="og:url" content="https://rakyt.id/kencana/karimunjawa-3h2m-open-trip">', false)
            ->assertSee('<link rel="canonical" href="https://rakyt.id/kencana/karimunjawa-3h2m-open-trip">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:site_name" content="Rakyt">', false)
            ->assertSee('<meta property="og:locale" content="id_ID">', false);

        $this->assertStringStartsWith('http', $cover->url(800));

        // The short description wins, cut to 200 characters; no variant yet → the original.
        Trip::acrossOrganizations()->whereKey($trip->id)->update(['short_description' => str_repeat('Laut biru ', 30)]);
        TripPhoto::acrossOrganizations()->whereKey($cover->id)->update(['variants' => '{}']);

        $html = $this->get('/kencana/karimunjawa-3h2m-open-trip')->getContent();
        preg_match('/<meta property="og:description" content="([^"]*)">/', $html, $match);
        $this->assertSame(200, mb_strlen(html_entity_decode($match[1])));
        $this->assertStringEndsWith('…', $match[1]);
        $this->assertStringContainsString('<meta property="og:image" content="'.$cover->url().'">', $html);
    }

    public function test_ac_31_head_tags_follow_visibility(): void
    {
        $draft = $this->tripFor($this->a, ['name' => 'Draf Rahasia', 'short_description' => 'Deskripsi rahasia']);
        $this->photoFor($this->a, $draft);

        $this->get('/kencana/draf-rahasia')->assertNotFound()->assertDontSee('og:', false)->assertDontSee('Draf Rahasia');
        $this->get('/kencana')->assertDontSee('Draf Rahasia')->assertDontSee('Deskripsi rahasia')->assertDontSee('trips/'.$draft->public_id, false);
    }

    public function test_ac_32_photos_use_their_variants_with_srcset_and_fall_back_to_the_original(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $full = $this->photoFor($this->a, $trip, 1);
        $partial = $this->photoFor($this->a, $trip, 2, [400]);
        $none = $this->photoFor($this->a, $trip, 3, []);

        $photos = $this->tripProps('kencana', 'karimunjawa')['trip']['photos'];

        $this->assertSame([
            ['src' => $full->url(400), 'srcset' => "{$full->url(400)} 400w, {$full->url(800)} 800w, {$full->url(1600)} 1600w"],
            ['src' => $partial->url(400), 'srcset' => "{$partial->url(400)} 400w"],
            ['src' => $none->url(), 'srcset' => null],
        ], $photos);
    }

    public function test_seat_counts_are_read_on_every_request(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $departure = $this->departureFor($this->a, $trip, '2024-10-24');
        $this->assertSame(16, $this->tripProps('kencana', 'karimunjawa')['departures'][0]['seats_left']);

        TripDeparture::acrossOrganizations()->whereKey($departure->id)->update(['seats_taken' => 14]);
        $this->assertSame(2, $this->tripProps('kencana', 'karimunjawa')['departures'][0]['seats_left']);
    }
}
