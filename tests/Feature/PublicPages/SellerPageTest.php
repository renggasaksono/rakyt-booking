<?php

namespace Tests\Feature\PublicPages;

use App\Models\Trip;
use App\Organizations\BookingSlug;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-002 section B: the seller page (mockups 1a, 3c) and its head tags (AC-30).
 */
class SellerPageTest extends TestCase
{
    use BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip', ['city' => 'Jepara, Jawa Tengah', 'bio' => 'Open trip Karimunjawa sejak 2018.']);
    }

    public function test_ac_10_the_header_shows_the_profile_and_follows_changes(): void
    {
        $props = $this->sellerProps();

        $this->assertSame('Kencana Trip', $props['organization']['name']);
        $this->assertSame('Jepara, Jawa Tengah', $props['organization']['city']);
        $this->assertSame('Open trip Karimunjawa sejak 2018.', $props['organization']['bio']);
        $this->assertNull($props['organization']['logo_url']);

        // Pengaturan → Profil usaha saves; the next load shows it (F-001 F1).
        $this->a->organization->update(['name' => 'Kencana Tour', 'bio' => null, 'logo_path' => "org/{$this->a->organization->public_id}/logo/abc.webp"]);
        $props = $this->sellerProps();

        $this->assertSame('Kencana Tour', $props['organization']['name']);
        $this->assertNull($props['organization']['bio']);
        $this->assertStringEndsWith('/logo/abc.webp', $props['organization']['logo_url']);
    }

    public function test_ac_10_profile_changes_from_pengaturan_show_on_the_next_load(): void
    {
        $this->actingAsTenant($this->a)->put(route('settings.profile'), [
            'name' => 'Kencana Baru',
            'city' => 'Semarang',
            'whatsapp' => '81277881020',
            'slug' => 'kencana',
            'bio' => 'Bio baru',
            'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $props = $this->sellerProps();
        $this->assertSame(['Kencana Baru', 'Semarang', 'Bio baru'], [$props['organization']['name'], $props['organization']['city'], $props['organization']['bio']]);
    }

    public function test_ac_11_chat_whatsapp_links_to_wa_me_with_the_seller_text(): void
    {
        $this->assertSame(
            'https://wa.me/6281277881020?text='.rawurlencode('Halo Kencana Trip, saya lihat halaman booking Anda di Rakyt. Mau tanya…'),
            $this->sellerProps()['organization']['whatsapp_url'],
        );
    }

    public function test_ac_12_the_share_link_is_the_booking_link(): void
    {
        $this->assertSame('https://rakyt.id/kencana', $this->sellerProps()['organization']['share_url']);
        $this->assertSame(BookingSlug::url('kencana'), $this->sellerProps()['organization']['share_url']);
    }

    public function test_the_booking_link_scheme_and_host_follow_config(): void
    {
        // Local dev points these at the app so copied links open on the machine.
        config(['rakyt.public_scheme' => 'http', 'rakyt.public_host' => 'rakyt-booking.test']);

        $this->assertSame('http://rakyt-booking.test/kencana', BookingSlug::url('kencana'));
        $this->assertSame('http://rakyt-booking.test/kencana', $this->sellerProps()['organization']['share_url']);
    }

    public function test_ac_13_trips_are_ordered_bookable_first_then_request_date_then_without_dates(): void
    {
        $zebra = $this->tripFor($this->a, ['name' => 'Zebra'], published: true);
        $this->departureFor($this->a, $zebra, '2024-11-14');
        $bukit = $this->tripFor($this->a, ['name' => 'Bukit'], published: true);
        $this->departureFor($this->a, $bukit, '2024-10-24');
        $anyer = $this->tripFor($this->a, ['name' => 'Anyer'], published: true);
        $this->departureFor($this->a, $anyer, '2024-10-24');
        // An earlier full departure does not count; its next bookable one does.
        $this->departureFor($this->a, $anyer, '2024-10-17', seatsTaken: 16);

        $oldPrivate = $this->published($this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private Lama'])), '2024-09-01');
        $newPrivate = $this->published($this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private Baru'])), '2024-10-01');
        $fullOnly = $this->published($this->tripFor($this->a, ['name' => 'Penuh Semua'], published: true), '2024-10-05');
        $this->departureFor($this->a, $fullOnly, '2024-10-24', seatsTaken: 16);
        $this->published($this->tripFor($this->a, ['name' => 'Tanpa Jadwal'], published: true), '2024-08-01');

        $this->assertSame(
            ['Anyer', 'Bukit', 'Zebra', 'Private Baru', 'Private Lama', 'Penuh Semua', 'Tanpa Jadwal'],
            array_column($this->sellerProps()['trips'], 'name'),
        );
        $this->assertNotNull($oldPrivate);
        $this->assertNotNull($newPrivate);
    }

    public function test_ac_14_cards_show_cover_price_and_one_status_line(): void
    {
        $scheduled = $this->tripFor($this->a, ['name' => 'Karimunjawa', 'base_price' => 1_250_000], published: true);
        $this->photoFor($this->a, $scheduled, 2, []);
        $cover = $this->photoFor($this->a, $scheduled, 1);
        $this->departureFor($this->a, $scheduled, '2024-10-24', seatsTaken: 4);
        $this->departureFor($this->a, $scheduled, '2024-11-14', ['price_override' => 1_150_000]);
        $this->asTenant($this->a, fn () => Trip::factory()->requestDate()->published()->create(['name' => 'Private', 'base_price' => 3_000_000]));
        $this->tripFor($this->a, ['name' => 'Kosong', 'base_price' => 500_000], published: true);

        $cards = collect($this->sellerProps()['trips'])->keyBy('name');

        $this->assertSame([
            'slug' => 'karimunjawa',
            'name' => 'Karimunjawa',
            'url' => route('public.trip', ['organization' => 'kencana', 'trip' => 'karimunjawa']),
            'cover' => ['src' => $cover->url(400), 'srcset' => $cover->url(400).' 400w, '.$cover->url(800).' 800w'],
            'from_price' => 1_150_000,
            'state' => 'bookable',
            'next_start_date' => '2024-10-24',
            'seats_left' => 12,
        ], $cards['Karimunjawa']);

        $this->assertSame(['request_date', null, null, 3_000_000, null], [$cards['Private']['state'], $cards['Private']['next_start_date'], $cards['Private']['seats_left'], $cards['Private']['from_price'], $cards['Private']['cover']]);
        $this->assertSame(['no_date', null, 500_000], [$cards['Kosong']['state'], $cards['Kosong']['next_start_date'], $cards['Kosong']['from_price']]);
    }

    public function test_ac_15_mulai_counts_only_bookable_departures_and_matches_the_editor_preview(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa', 'base_price' => 1_250_000], published: true);
        $this->departureFor($this->a, $trip, '2024-10-24');
        $this->departureFor($this->a, $trip, '2024-11-14', ['price_override' => 1_150_000]);
        $this->departureFor($this->a, $trip, '2024-11-21', ['price_override' => 900_000, 'status' => 'closed', 'closed_reason' => 'manual']);
        $this->departureFor($this->a, $trip, '2024-11-28', ['price_override' => 800_000], seatsTaken: 16);
        // Deadline passed, auto-close has not run yet: not bookable (F-003 AC-36).
        $this->departureFor($this->a, $trip, '2024-10-12', ['price_override' => 700_000]);
        $this->departureFor($this->a, $trip, '2024-10-01', ['price_override' => 600_000]);

        $card = $this->sellerProps()['trips'][0];
        $this->assertSame(1_150_000, $card['from_price']);
        $this->assertSame(1_150_000, $this->tripProps('kencana', 'karimunjawa')['trip']['from_price']);

        $this->actingAsTenant($this->a)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('preview.from_price', $card['from_price'])
                ->where('preview.next_departure', ['start_date' => '2024-10-24', 'seats_left' => 16]));
        auth()->logout();

        // No bookable departure: the base price, on the card and in the preview.
        $empty = $this->tripFor($this->a, ['name' => 'Kosong', 'base_price' => 2_000_000], published: true);
        $this->departureFor($this->a, $empty, '2024-10-24', ['price_override' => 100_000], seatsTaken: 16);
        $this->assertSame(2_000_000, collect($this->sellerProps()['trips'])->firstWhere('name', 'Kosong')['from_price']);
        $this->actingAsTenant($this->a)->get(route('trips.edit', $empty))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('preview.from_price', 2_000_000)->where('preview.next_departure', null));
    }

    public function test_ac_15_a_draft_previews_what_its_card_will_show(): void
    {
        $draft = $this->tripFor($this->a, ['base_price' => 1_250_000]);
        $this->departureFor($this->a, $draft, '2024-10-24', ['price_override' => 1_000_000]);

        $this->actingAsTenant($this->a)->get(route('trips.edit', $draft))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('preview.from_price', 1_000_000));
    }

    public function test_ac_16_without_published_trips_the_page_shows_the_empty_state(): void
    {
        $this->tripFor($this->a, ['name' => 'Draf Saja']);
        $onboarding = $this->seller('baru', 'Usaha Baru', ['onboarding_skipped_at' => null]);
        $this->assertTrue($onboarding->organization->fresh()->isOnboarding());

        foreach (['kencana', 'baru'] as $slug) {
            $props = $this->sellerProps($slug);
            $this->assertSame([], $props['trips']);
            $this->assertSame('Belum ada trip yang bisa dipesan saat ini.', $props['copy']['seller']['empty']);
            $this->assertSame('Chat operator untuk info jadwal.', $props['copy']['seller']['empty_hint']);
            $this->assertNotEmpty($props['organization']['whatsapp_url']);
            $this->assertNotEmpty($props['organization']['share_url']);
        }

        $this->get('/kencana')->assertDontSee('Draf Saja')->assertDontSee('draf-saja');
    }

    public function test_ac_30_seller_head_tags_are_server_rendered(): void
    {
        $trip = $this->tripFor($this->a, ['name' => 'Karimunjawa'], published: true);
        $cover = $this->photoFor($this->a, $trip);

        $this->get('/kencana')
            ->assertSee('<title>Kencana Trip · Rakyt Booking</title>', false)
            ->assertSee('<meta property="og:title" content="Kencana Trip">', false)
            ->assertSee('<meta property="og:description" content="Open trip Karimunjawa sejak 2018.">', false)
            ->assertSee('<meta property="og:image" content="'.$cover->url(800).'">', false)
            ->assertSee('<meta property="og:url" content="https://rakyt.id/kencana">', false)
            ->assertSee('<link rel="canonical" href="https://rakyt.id/kencana">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:site_name" content="Rakyt Booking">', false)
            ->assertSee('<meta property="og:locale" content="id_ID">', false);

        // No bio: "n trip · Kota basis"; the logo wins over the cover.
        $this->a->organization->update(['bio' => null, 'logo_path' => "org/{$this->a->organization->public_id}/logo/abc.webp"]);
        $this->get('/kencana')
            ->assertSee('<meta property="og:description" content="1 trip · Jepara, Jawa Tengah">', false)
            ->assertSee('<meta property="og:image" content="'.$this->a->organization->fresh()->logoUrl().'">', false);

        // No published trip: just Kota basis, and no image without a logo.
        $this->a->organization->update(['logo_path' => null]);
        Trip::acrossOrganizations()->whereKey($trip->id)->update(['status' => 'draft']);
        $this->get('/kencana')
            ->assertSee('<meta property="og:description" content="Jepara, Jawa Tengah">', false)
            ->assertDontSee('og:image', false);
    }

    public function test_the_seller_page_runs_a_bounded_number_of_queries(): void
    {
        $this->tripsWithPhotosAndDepartures(1);
        $one = $this->countQueries(fn () => $this->get('/kencana')->assertOk());

        $this->tripsWithPhotosAndDepartures(9);
        $ten = $this->countQueries(fn () => $this->get('/kencana')->assertOk());

        $this->assertCount(10, $this->sellerProps()['trips']);
        $this->assertSame($one, $ten);
        $this->assertLessThanOrEqual(6, $ten);
    }

    private function tripsWithPhotosAndDepartures(int $count): void
    {
        foreach (range(1, $count) as $i) {
            $trip = $this->tripFor($this->a, [], published: true);
            $this->photoFor($this->a, $trip, 1);
            $this->photoFor($this->a, $trip, 2);
            $this->departureFor($this->a, $trip, '2024-10-24');
            $this->departureFor($this->a, $trip, '2024-11-14', seatsTaken: 16);
        }
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $callback();
        DB::getEventDispatcher()->forget(QueryExecuted::class);

        return $count;
    }

    private function published(Trip $trip, string $date): Trip
    {
        Trip::acrossOrganizations()->whereKey($trip->id)->update(['published_at' => $date.' 00:00:00']);

        return $trip;
    }
}
