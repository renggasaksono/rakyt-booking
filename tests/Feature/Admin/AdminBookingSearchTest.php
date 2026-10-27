<?php

namespace Tests\Feature\Admin;

use App\Bookings\BookingStatus;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-013: the admin booking search (`/admin/pesanan`) — bookings across every
 * organization, by exact reference, E.164 phone, customer or seller name;
 * filtered by status, seller and departure date; paginated; behind the F-010
 * gate. The clock starts at Thursday 2024-10-10 10:00 WIB.
 */
class AdminBookingSearchTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    private Tenant $kencana;

    private Tenant $bromo;

    private User $admin;

    private Trip $karimunTrip;

    private Trip $sunriseTrip;

    private TripDeparture $karimun;

    private TripDeparture $sunrise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->kencana = $this->seller('kencana', 'Kencana Trip');
        $this->bromo = $this->seller('bromo-adventure', 'Bromo Adventure');
        $this->admin = $this->platformAdmin();
        $this->karimunTrip = $this->bookableTrip($this->kencana, ['name' => 'Karimunjawa 3H2M']);
        $this->sunriseTrip = $this->bookableTrip($this->bromo, ['name' => 'Bromo Sunrise']);
        $this->karimun = $this->departureFor($this->kencana, $this->karimunTrip, '2024-10-24');
        $this->sunrise = $this->departureFor($this->bromo, $this->sunriseTrip, '2024-11-07');
    }

    public function test_ac_6_the_search_is_behind_the_admin_gate(): void
    {
        $this->bookingFor($this->kencana, $this->karimun, attributes: ['customer_name' => 'Siti Aminah']);
        $url = route('admin.bookings.index', ['cari' => 'siti']);

        $this->get($url)->assertRedirect(route('login'));

        // A seller is forbidden, HTML or JSON, and sees no booking.
        $this->actingAsTenant($this->kencana)->get($url)->assertForbidden()->assertDontSee('Siti Aminah');
        $this->actingAsTenant($this->kencana)->getJson($url)->assertForbidden()->assertDontSee('Siti Aminah');

        // An admin who has not passed TOTP is sent to the challenge.
        $this->actingAs($this->admin)->get($url)->assertRedirect(route('admin.two-factor.challenge'));
        $this->actingAs($this->admin)->getJson($url)->assertRedirect(route('admin.two-factor.challenge'));

        $this->actingAsConfirmedAdmin($this->admin)->get($url)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Admin/Bookings/Index')->has('bookings', 1));
    }

    public function test_bookings_of_every_organization_are_listed_newest_first_with_their_columns(): void
    {
        $older = $this->bookingFor($this->kencana, $this->karimun, 2, attributes: ['customer_name' => 'Siti Aminah']);
        $this->travel(5)->minutes();
        $newer = $this->bookingFor($this->bromo, $this->sunrise, 3, BookingStatus::Paid, ['customer_name' => 'Budi Santoso', 'unit_price' => 900_000]);

        $this->index()->assertInertia(fn ($page) => $page
            ->has('bookings', 2)
            ->where('bookings.0.id', $newer->public_id)
            ->where('bookings.1.id', $older->public_id)
            ->where('bookings.1.reference', $older->reference)
            ->where('bookings.1.customer_name', 'Siti Aminah')
            ->where('bookings.1.participants', 2)
            ->where('bookings.1.total_amount', 2_500_000)
            ->where('bookings.1.total_label', 'Rp2.500.000')
            ->where('bookings.1.status', 'awaiting_payment')
            ->where('bookings.1.status_label', 'Menunggu pembayaran')
            ->where('bookings.1.trip_name', 'Karimunjawa 3H2M')
            ->where('bookings.1.departure_label', '24–26 Okt')
            ->where('bookings.1.created_at', '2024-10-10T03:00:00Z')
            ->where('bookings.1.created_label', '10 Okt 2024 10.00 WIB')
            ->where('bookings.1.organization', [
                'id' => $this->kencana->id(),
                'name' => 'Kencana Trip',
                'slug' => 'kencana',
                'status' => 'active',
                'status_label' => 'Aktif',
                // Results link to F-012's seller detail.
                'url' => route('admin.sellers.show', $this->kencana->id()),
            ])
            ->where('bookings.1.url', route('admin.bookings.show', $older->public_id))
            ->where('bookings.0.trip_name', 'Bromo Sunrise')
            ->where('bookings.0.total_amount', 2_700_000)
            ->where('bookings.0.status_label', 'Lunas')
            ->where('bookings.0.organization.name', 'Bromo Adventure')
        );
    }

    public function test_ac_1_an_exact_reference_finds_that_booking_in_any_organization(): void
    {
        $theirs = $this->bookingFor($this->bromo, $this->sunrise, attributes: ['reference' => 'RKB-BRM-1107-0142']);
        $this->bookingFor($this->bromo, $this->sunrise, attributes: ['reference' => 'RKB-BRM-1107-1420']);
        $this->bookingFor($this->kencana, $this->karimun, attributes: ['reference' => 'RKB-KNC-1024-0142']);

        foreach (['RKB-BRM-1107-0142', ' rkb-brm-1107-0142 '] as $term) {
            $this->assertSame([$theirs->public_id], $this->ids(['cari' => $term]), $term);
        }

        $this->index(['cari' => 'RKB-BRM-1107-0142'])->assertInertia(fn ($page) => $page->where('search_kind', 'reference'));

        // Exact only: a part of a reference is not a reference.
        $this->assertSame([], $this->ids(['cari' => 'RKB-BRM-1107-014']));
        $this->assertSame([], $this->ids(['cari' => 'RKB-XYZ-1107-0142']));
    }

    public function test_ac_1_the_reference_lookup_uses_its_own_index(): void
    {
        $indexes = collect(Schema::getIndexes('bookings'))->pluck('columns')->all();

        $this->assertContains(['reference'], $indexes);
        $this->assertContains(['customer_phone'], $indexes);
        $this->assertContains(['created_at', 'id'], $indexes);
        $this->assertContains(['start_date'], collect(Schema::getIndexes('trip_departures'))->pluck('columns')->all());
    }

    public function test_ac_2_a_phone_in_any_format_is_normalised_to_e164_and_matched_exactly(): void
    {
        $match = $this->bookingFor($this->bromo, $this->sunrise, attributes: ['customer_phone' => '+6281234567890']);
        $this->bookingFor($this->kencana, $this->karimun, attributes: ['customer_phone' => '+6285700001111']);
        // Same trailing digits, different number.
        $this->bookingFor($this->kencana, $this->karimun, attributes: ['customer_phone' => '+62881234567890']);

        foreach (['081234567890', '0812 3456 7890', '+62 812-3456-7890', '6281234567890', '(+62) 812.3456.7890', '81234567890'] as $term) {
            $this->assertSame([$match->public_id], $this->ids(['cari' => $term]), $term);
        }

        $this->index(['cari' => '0812 3456 7890'])->assertInertia(fn ($page) => $page->where('search_kind', 'phone'));

        // Not a whole mobile number: no phone match.
        $this->assertSame([], $this->ids(['cari' => '7890']));
        $this->assertSame([], $this->ids(['cari' => '021 555 1234']));
    }

    public function test_search_by_customer_name_or_seller_name_or_slug(): void
    {
        $siti = $this->bookingFor($this->kencana, $this->karimun, attributes: ['customer_name' => 'Siti Aminah']);
        $this->travel(1)->minutes();
        $budi = $this->bookingFor($this->bromo, $this->sunrise, attributes: ['customer_name' => 'Budi Santoso']);

        $this->assertSame([$siti->public_id], $this->ids(['cari' => 'aminah']), 'Customer name part, any case.');
        $this->assertSame([$budi->public_id], $this->ids(['cari' => 'Bromo Adv']), 'Seller name.');
        $this->assertSame([$budi->public_id], $this->ids(['cari' => 'bromo-adv']), 'Seller slug.');
        $this->assertSame([], $this->ids(['cari' => 'tidak-ada']));
        // LIKE wildcards are literal.
        $this->assertSame([], $this->ids(['cari' => '%_%']));

        $this->index(['cari' => 'aminah'])->assertInertia(fn ($page) => $page->where('search_kind', 'text'));
    }

    public function test_ac_3_filter_by_status(): void
    {
        $waiting = $this->bookingFor($this->kencana, $this->karimun);
        $paid = $this->bookingFor($this->bromo, $this->sunrise, 1, BookingStatus::Paid);
        $cancelled = $this->bookingFor($this->kencana, $this->karimun, 1, BookingStatus::Cancelled);
        $review = $this->setStatus($this->bookingFor($this->bromo, $this->sunrise, 1), BookingStatus::NeedsReview);

        $this->assertSame([$waiting->public_id], $this->ids(['status' => 'awaiting_payment']));
        $this->assertSame([$review->public_id], $this->ids(['status' => 'needs_review']));
        $this->assertSame([$paid->public_id], $this->ids(['status' => 'paid']));
        $this->assertSame([$cancelled->public_id], $this->ids(['status' => 'cancelled']));
        $this->assertSame([], $this->ids(['status' => 'rejected']));

        $this->index()->assertInertia(fn ($page) => $page
            ->where('options.statuses', [
                ['value' => 'awaiting_payment', 'label' => 'Menunggu pembayaran'],
                ['value' => 'needs_review', 'label' => 'Perlu dicek'],
                ['value' => 'paid', 'label' => 'Lunas'],
                ['value' => 'rejected', 'label' => 'Ditolak'],
                ['value' => 'cancelled', 'label' => 'Dibatalkan'],
                ['value' => 'completed', 'label' => 'Selesai'],
            ])
        );
    }

    public function test_ac_3_filter_by_organization_with_the_picker(): void
    {
        $theirs = $this->bookingFor($this->bromo, $this->sunrise);
        $this->bookingFor($this->kencana, $this->karimun);

        $this->assertSame([$theirs->public_id], $this->ids(['organisasi' => (string) $this->bromo->id()]));

        $this->index(['organisasi' => $this->bromo->id()])->assertInertia(fn ($page) => $page
            ->where('filters.organisasi', (string) $this->bromo->id())
            ->where('selected_organization', ['id' => (string) $this->bromo->id(), 'name' => 'Bromo Adventure', 'slug' => 'bromo-adventure'])
            ->where('urls.organization_lookup', route('admin.sellers.lookup'))
        );
    }

    public function test_ac_3_filter_by_departure_date(): void
    {
        $early = $this->bookingFor($this->kencana, $this->karimun);
        $late = $this->bookingFor($this->bromo, $this->sunrise);

        $this->assertSame([$early->public_id], $this->ids(['dari' => '2024-10-24', 'sampai' => '2024-10-24']));
        $this->assertSame([$late->public_id], $this->ids(['dari' => '2024-10-25']));
        $this->assertSame([$early->public_id], $this->ids(['sampai' => '2024-11-06']));
    }

    public function test_ac_3_filters_combine_and_persist_in_the_url(): void
    {
        $match = $this->bookingFor($this->bromo, $this->sunrise, 1, BookingStatus::Paid, ['customer_name' => 'Siti Aminah']);
        // Each differs from the match in one filter only.
        $this->bookingFor($this->bromo, $this->sunrise, 1, attributes: ['customer_name' => 'Siti Aminah']);
        $this->bookingFor($this->bromo, $this->sunrise, 1, BookingStatus::Paid, ['customer_name' => 'Budi Santoso']);
        $this->bookingFor($this->kencana, $this->karimun, 1, BookingStatus::Paid, ['customer_name' => 'Siti Aminah']);
        $other = $this->departureFor($this->bromo, $this->sunriseTrip, '2024-12-01');
        $this->bookingFor($this->bromo, $other, 1, BookingStatus::Paid, ['customer_name' => 'Siti Aminah']);

        $query = ['cari' => 'siti', 'status' => 'paid', 'organisasi' => (string) $this->bromo->id(), 'dari' => '2024-11-01', 'sampai' => '2024-11-30'];

        $this->index($query)->assertInertia(fn ($page) => $page
            ->has('bookings', 1)
            ->where('bookings.0.id', $match->public_id)
            ->where('filters', $query)
            // The detail link carries the filters, so its "←" restores them.
            ->where('bookings.0.url', route('admin.bookings.show', [$match->public_id, ...$query]))
        );
    }

    public function test_ac_3_results_are_paginated_and_pages_keep_the_filters(): void
    {
        $large = $this->departureFor($this->kencana, $this->karimunTrip, '2024-10-31', ['capacity' => 40]);
        $bookings = [];
        for ($i = 0; $i < AdminBookingController::PER_PAGE + 2; $i++) {
            $bookings[] = $this->bookingFor($this->kencana, $large, 1, attributes: ['customer_name' => "Pelanggan {$i}"]);
            $this->travel(1)->minutes();
        }

        $this->index(['cari' => 'pelanggan', 'status' => 'awaiting_payment'])->assertInertia(fn ($page) => $page
            ->has('bookings', AdminBookingController::PER_PAGE)
            ->where('bookings.0.id', end($bookings)->public_id)
            ->where('pagination.page', 1)
            ->where('pagination.prev_url', null)
            ->where('pagination.next_url', route('admin.bookings.index', ['cari' => 'pelanggan', 'status' => 'awaiting_payment', 'halaman' => 2]))
        );

        $this->index(['cari' => 'pelanggan', 'status' => 'awaiting_payment', 'halaman' => 2])->assertInertia(fn ($page) => $page
            ->has('bookings', 2)
            ->where('bookings.1.id', $bookings[0]->public_id)
            ->where('pagination.page', 2)
            ->where('pagination.next_url', null)
            ->where('pagination.prev_url', route('admin.bookings.index', ['cari' => 'pelanggan', 'status' => 'awaiting_payment']))
        );
    }

    public function test_invalid_filters_are_dropped_and_the_list_still_renders(): void
    {
        $this->bookingFor($this->kencana, $this->karimun);

        $this->actingAsConfirmedAdmin($this->admin)
            ->get(route('admin.bookings.index').'?status=hapus&halaman=-3&cari[]=x&organisasi=abc&dari=2024-13-01&sampai=kemarin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('bookings', 1)
                ->where('filters', ['cari' => null, 'status' => null, 'organisasi' => null, 'dari' => null, 'sampai' => null])
                ->where('search_kind', null)
                ->where('pagination.page', 1)
            );

        // An unknown organization, a reversed range, an over-long or control-character term.
        $this->index(['organisasi' => '999999', 'dari' => '2024-11-01', 'sampai' => '2024-10-01', 'cari' => str_repeat('a', 101)])
            ->assertInertia(fn ($page) => $page
                ->has('bookings', 1)
                ->where('filters', ['cari' => null, 'status' => null, 'organisasi' => null, 'dari' => null, 'sampai' => null])
                ->where('selected_organization', null)
            );
        $this->index(['cari' => "siti\x07"])->assertInertia(fn ($page) => $page->where('filters.cari', null));
        $this->index(['organisasi' => '9999999999999999999999'])->assertOk();
    }

    public function test_a_suspended_sellers_bookings_show_its_status(): void
    {
        $this->bookingFor($this->kencana, $this->karimun);
        $this->suspendOrganization($this->kencana);

        $this->index()->assertInertia(fn ($page) => $page
            ->where('bookings.0.organization.status', 'suspended')
            ->where('bookings.0.organization.status_label', 'Ditangguhkan')
        );
    }

    public function test_ac_5_the_list_never_carries_a_customer_phone_or_secret(): void
    {
        $booking = $this->bookingFor($this->kencana, $this->karimun, attributes: ['customer_phone' => '+6281299887766', 'notes' => 'CATATAN-RAHASIA']);
        $booking = Booking::acrossOrganizations()->findOrFail($booking->id);

        // Browsing or searching by anything but the phone never reveals it.
        $response = $this->index(['cari' => 'Rina']);
        $response->assertInertia(fn ($page) => $page->has('bookings', 1));
        $response->assertDontSee('81299887766', false);

        $rows = json_encode($this->propsOf($response, 'Admin/Bookings/Index')['bookings']);
        foreach (['81299887766', '9887766', 'CATATAN-RAHASIA', $booking->access_token, $booking->access_token_hash, $booking->submission_key, '/pesanan/'.$booking->access_token] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $rows);
        }

        // Searching by the phone finds the booking; only the admin's own term comes back (filters, links).
        $byPhone = $this->propsOf($this->index(['cari' => '081299887766']), 'Admin/Bookings/Index')['bookings'];
        $this->assertCount(1, $byPhone);
        $this->assertStringNotContainsString('9887766', (string) json_encode(collect($byPhone[0])->except('url')->all()));

        $this->assertSame(
            ['created_at', 'created_label', 'customer_name', 'departure_label', 'id', 'organization', 'participants', 'reference', 'status', 'status_label', 'total_amount', 'total_label', 'trip_name', 'url'],
            collect($this->propsOf($response, 'Admin/Bookings/Index')['bookings'][0])->keys()->sort()->values()->all(),
        );
    }

    public function test_ac_5_there_is_no_export_and_the_feature_is_read_only(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'admin.bookings.'))
            ->mapWithKeys(fn (Route $route) => [$route->getName() => $route->methods()])
            ->sortKeys()
            ->all();

        $this->assertSame([
            'admin.bookings.index' => ['GET', 'HEAD'],
            'admin.bookings.show' => ['GET', 'HEAD'],
        ], $routes);
    }

    public function test_the_list_runs_a_fixed_number_of_queries(): void
    {
        $this->bookingFor($this->kencana, $this->karimun);
        $query = ['organisasi' => (string) $this->bromo->id()];
        $baseline = count($this->queriesOf(fn () => $this->index()->assertOk()));
        $filtered = count($this->queriesOf(fn () => $this->index($query)->assertOk()));

        foreach (range(1, 5) as $i) {
            $tenant = $this->seller("seller-{$i}", "Seller {$i}");
            $departure = $this->departureFor($tenant, $this->bookableTrip($tenant), '2024-10-2'.$i);
            $this->bookingFor($tenant, $departure);
            $this->bookingFor($this->bromo, $this->sunrise);
        }

        $this->assertSame($baseline, count($this->queriesOf(fn () => $this->index()->assertOk())));
        $this->assertSame($filtered, count($this->queriesOf(fn () => $this->index($query)->assertOk())));
    }

    public function test_the_admin_nav_links_to_the_booking_search(): void
    {
        $booking = $this->bookingFor($this->kencana, $this->karimun);

        foreach ([route('admin.bookings.index'), route('admin.bookings.show', $booking->public_id)] as $url) {
            $this->actingAsConfirmedAdmin($this->admin)->get($url)
                ->assertInertia(fn ($page) => $page
                    ->where('admin.nav.2.key', 'bookings')
                    ->where('admin.nav.2.label', 'Pesanan')
                    ->where('admin.nav.2.url', route('admin.bookings.index'))
                    ->where('admin.nav.2.active', true)
                    ->where('admin.nav.1.active', false)
                );
        }
    }

    public function test_ac_7_the_search_is_phone_first(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Admin/Bookings/Index.vue'));

        // Results are cards that stack; inputs and links keep 44 px+ targets; no gold CTA in admin.
        $this->assertStringNotContainsString('<table', $vue);
        $this->assertStringContainsString('min-h-12', $vue);
        $this->assertStringContainsString('min-h-11', $vue);
        $this->assertStringNotContainsString('prada', $vue);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function index(array $query = []): TestResponse
    {
        return $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.bookings.index', $query));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function ids(array $query): array
    {
        return array_column($this->propsOf($this->index($query)->assertOk(), 'Admin/Bookings/Index')['bookings'], 'id');
    }
}
