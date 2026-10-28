<?php

namespace Tests\Feature\Admin;

use App\Bookings\BookingStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\TestCase;

/**
 * F-011: the admin overview (`/admin`) — five live counts across every
 * organization (sellers with suspended apart from active, published trips,
 * bookings, pending and pending-verification bookings), each linking to the
 * matching filtered list of F-012 or F-013; behind the F-010 gate.
 */
class AdminOverviewDashboardTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->admin = $this->platformAdmin();
    }

    public function test_ac_5_the_overview_is_only_reachable_through_the_admin_gate(): void
    {
        $kencana = $this->seller('kencana', 'Kencana Trip');
        $url = route('admin.dashboard');

        $this->get($url)->assertRedirect(route('login'));

        // A seller is forbidden, HTML or JSON, and sees no count.
        $this->actingAsTenant($kencana)->get($url)->assertForbidden()->assertDontSee('stats');
        $this->actingAsTenant($kencana)->getJson($url)->assertForbidden()->assertDontSee('stats');

        // An admin who has not passed TOTP is sent to the challenge.
        $this->actingAs($this->admin)->get($url)->assertRedirect(route('admin.two-factor.challenge'));
        $this->actingAs($this->admin)->getJson($url)->assertRedirect(route('admin.two-factor.challenge'));

        $this->overview()
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Admin/Dashboard')->where('stats.sellers.count', 1));
    }

    public function test_ac_1_ac_2_ac_4_the_five_counts_span_every_organization(): void
    {
        $kencana = $this->seller('kencana', 'Kencana Trip');
        $bromo = $this->seller('bromo-adventure', 'Bromo Adventure');
        $rinjani = $this->seller('rinjani', 'Rinjani Trek');

        $karimun = $this->departureFor($kencana, $this->bookableTrip($kencana), '2024-10-24');
        $this->tripFor($kencana, ['name' => 'Draf Kencana']);
        $sunrise = $this->departureFor($bromo, $this->bookableTrip($bromo, ['name' => 'Bromo Sunrise']), '2024-11-07');
        $this->bookableTrip($bromo, ['name' => 'Ijen Blue Fire']);
        $this->tripFor($rinjani, ['name' => 'Draf Rinjani']);

        // Every status once in Kencana, a second set of pending ones in Bromo.
        foreach (BookingStatus::cases() as $status) {
            $this->bookingFor($kencana, $karimun, 1, $status);
        }
        $this->bookingFor($bromo, $sunrise, 2, BookingStatus::AwaitingPayment);
        $this->bookingFor($bromo, $sunrise, 2, BookingStatus::NeedsReview);
        $this->bookingFor($bromo, $sunrise, 2, BookingStatus::NeedsReview);

        // AC-4: a suspended seller is still counted, apart from the active ones.
        $this->suspendOrganization($rinjani);

        $this->overview()->assertInertia(fn ($page) => $page
            ->where('stats.sellers.count', 3)
            ->where('stats.sellers.active.count', 2)
            ->where('stats.sellers.suspended.count', 1)
            ->where('stats.published_trips.count', 3)
            ->where('stats.bookings.count', 9)
            // AC-2: one status each, on the single BookingStatus column.
            ->where('stats.awaiting_payment.count', 2)
            ->where('stats.needs_review.count', 3)
        );
    }

    public function test_ac_1_counts_are_zero_on_an_empty_platform(): void
    {
        $this->overview()->assertInertia(fn ($page) => $page
            ->where('stats.sellers.count', 0)
            ->where('stats.sellers.active.count', 0)
            ->where('stats.sellers.suspended.count', 0)
            ->where('stats.published_trips.count', 0)
            ->where('stats.bookings.count', 0)
            ->where('stats.awaiting_payment.count', 0)
            ->where('stats.needs_review.count', 0)
        );
    }

    public function test_ac_1_counts_are_live(): void
    {
        $kencana = $this->seller('kencana', 'Kencana Trip');
        $departure = $this->departureFor($kencana, $this->bookableTrip($kencana));
        $booking = $this->bookingFor($kencana, $departure);

        $this->overview()->assertInertia(fn ($page) => $page
            ->where('stats.awaiting_payment.count', 1)
            ->where('stats.needs_review.count', 0)
            ->where('stats.sellers.suspended.count', 0)
        );

        $this->setStatus($booking, BookingStatus::NeedsReview);
        $this->suspendOrganization($kencana);

        $this->overview()->assertInertia(fn ($page) => $page
            ->where('stats.awaiting_payment.count', 0)
            ->where('stats.needs_review.count', 1)
            ->where('stats.sellers.active.count', 0)
            ->where('stats.sellers.suspended.count', 1)
        );
    }

    public function test_ac_1_the_counts_read_organizations_trips_and_bookings_in_three_queries(): void
    {
        $kencana = $this->seller('kencana', 'Kencana Trip');
        $this->bookingFor($kencana, $this->departureFor($kencana, $this->bookableTrip($kencana)));
        // Settle the session and the admin's own reads first.
        $this->overview()->assertOk();

        $queries = $this->queriesOf(fn () => $this->overview()->assertOk());
        $counts = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'count(')));

        $this->assertCount(3, $counts);
        $this->assertMatchesRegularExpression('/from "organizations"/', $counts[0]);
        $this->assertMatchesRegularExpression('/from "trips"/', $counts[1]);
        $this->assertMatchesRegularExpression('/from "bookings"/', $counts[2]);

        // Across organizations by design: none names one.
        foreach ($counts as $sql) {
            $this->assertStringNotContainsString('organization_id', $sql);
        }
    }

    public function test_ac_1_each_count_has_an_index_that_does_not_lead_with_the_organization(): void
    {
        $leading = fn (string $table) => collect(Schema::getIndexes($table))->map(fn (array $index) => $index['columns'][0] ?? null)->all();

        $this->assertContains('status', $leading('organizations'));
        $this->assertContains('status', $leading('trips'));
        $this->assertContains('status', $leading('bookings'));
    }

    public function test_ac_3_each_count_links_to_its_filtered_list(): void
    {
        $this->overview()->assertInertia(fn ($page) => $page
            ->where('stats.sellers.url', route('admin.sellers.index'))
            ->where('stats.sellers.active.url', route('admin.sellers.index', ['status' => 'active']))
            ->where('stats.sellers.suspended.url', route('admin.sellers.index', ['status' => 'suspended']))
            ->where('stats.published_trips.url', route('admin.sellers.index', ['trip' => 'published']))
            ->where('stats.bookings.url', route('admin.bookings.index'))
            ->where('stats.awaiting_payment.url', route('admin.bookings.index', ['status' => 'awaiting_payment']))
            ->where('stats.needs_review.url', route('admin.bookings.index', ['status' => 'needs_review']))
        );
    }

    public function test_ac_3_the_linked_lists_hold_what_was_counted(): void
    {
        $kencana = $this->seller('kencana', 'Kencana Trip');
        $bromo = $this->seller('bromo-adventure', 'Bromo Adventure');
        $rinjani = $this->seller('rinjani', 'Rinjani Trek');
        $karimun = $this->departureFor($kencana, $this->bookableTrip($kencana));
        $this->tripFor($bromo, ['name' => 'Draf Bromo']);
        $this->bookingFor($kencana, $karimun, 1, BookingStatus::AwaitingPayment);
        $this->bookingFor($kencana, $karimun, 1, BookingStatus::NeedsReview);
        $this->bookingFor($kencana, $karimun, 1, BookingStatus::Paid);
        $this->suspendOrganization($rinjani);

        $stats = $this->stats();

        $this->assertSame($stats['sellers']['count'], $this->listed($stats['sellers']['url'], 'sellers'));
        $this->assertSame($stats['sellers']['active']['count'], $this->listed($stats['sellers']['active']['url'], 'sellers'));
        $this->assertSame($stats['sellers']['suspended']['count'], $this->listed($stats['sellers']['suspended']['url'], 'sellers'));
        $this->assertSame($stats['bookings']['count'], $this->listed($stats['bookings']['url'], 'bookings'));
        $this->assertSame($stats['awaiting_payment']['count'], $this->listed($stats['awaiting_payment']['url'], 'bookings'));
        $this->assertSame($stats['needs_review']['count'], $this->listed($stats['needs_review']['url'], 'bookings'));

        // Published trips open the sellers that have one (trips are read per seller, F-012).
        $this->assertSame(1, $stats['published_trips']['count']);
        $this->assertSame(1, $this->listed($stats['published_trips']['url'], 'sellers'));
    }

    public function test_the_overview_copy_is_in_bahasa_indonesia(): void
    {
        $this->overview()->assertInertia(fn ($page) => $page
            ->where('copy.dashboard.heading', 'Ringkasan platform')
            ->where('copy.dashboard.stats.sellers', 'Penjual')
            ->where('copy.dashboard.stats.active', 'Aktif')
            ->where('copy.dashboard.stats.suspended', 'Ditangguhkan')
            ->where('copy.dashboard.stats.published_trips', 'Trip tampil')
            ->where('copy.dashboard.stats.bookings', 'Total pesanan')
            ->where('copy.dashboard.stats.awaiting_payment', 'Menunggu pembayaran')
            ->where('copy.dashboard.stats.needs_review', 'Perlu dicek')
            ->where('admin.nav.0.key', 'overview')
            ->where('admin.nav.0.active', true)
        );
    }

    public function test_ac_6_the_overview_is_phone_first(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Admin/Dashboard.vue'));

        // One column of cards on a phone, two from 640 px; links keep 44 px+ targets; no table.
        $this->assertStringContainsString('grid-cols-1', $vue);
        $this->assertStringContainsString('sm:grid-cols-2', $vue);
        $this->assertStringContainsString('min-h-11', $vue);
        $this->assertStringNotContainsString('<table', $vue);
    }

    private function overview(): TestResponse
    {
        return $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        return $this->propsOf($this->overview(), 'Admin/Dashboard')['stats'];
    }

    private function listed(string $url, string $key): int
    {
        $count = 0;

        $this->actingAsConfirmedAdmin($this->admin)->get($url)
            ->assertOk()
            ->assertInertia(function ($page) use (&$count, $key) {
                $count = count($page->toArray()['props'][$key]);
            });

        return $count;
    }
}
