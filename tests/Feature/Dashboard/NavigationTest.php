<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingStatus;
use App\Dashboard\DashboardPresenter;
use App\Models\TripDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsOnboarding;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-007 section A: landing (AC-1), desktop and phone navigation (AC-2,
 * AC-3) and the Perlu tindakan counts (AC-4). The clock starts at Thursday
 * 2024-10-10 10:00 WIB.
 */
class NavigationTest extends TestCase
{
    use BuildsBookings, BuildsDashboard, BuildsOnboarding, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
    }

    public function test_ac_1_sellers_land_on_hari_ini_and_dashboard_redirects_there(): void
    {
        $this->assertSame(url('/hari-ini'), route('dashboard'));

        $this->post(route('login'), ['email' => $this->a->owner->email, 'password' => 'password'])->assertRedirect('/hari-ini');
        $this->get('/hari-ini')->assertOk();
        $this->get('/dashboard')->assertStatus(302)->assertRedirect('/hari-ini');
        // Finished onboarding (F-001 E3) goes to the same place.
        $this->get(route('onboarding.trip'))->assertRedirect('/hari-ini');
    }

    public function test_ac_1_a_seller_in_onboarding_resumes_and_a_guest_signs_in(): void
    {
        $this->get('/hari-ini')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));

        $onboarding = $this->onboardingTenant();
        $this->actingAsTenant($onboarding)->get('/hari-ini')->assertRedirect();
        $this->assertNotSame(url('/hari-ini'), $this->actingAsTenant($onboarding)->get('/hari-ini')->headers->get('Location'));
        $this->assertStringContainsString('/mulai', (string) $this->actingAsTenant($onboarding)->get('/hari-ini')->headers->get('Location'));
    }

    public function test_ac_2_the_desktop_nav_lists_six_items_in_order(): void
    {
        $nav = $this->todayProps($this->a)['seller']['nav'];

        $this->assertSame(['today', 'bookings', 'trips', 'payments', 'public_page', 'settings'], array_column($nav, 'key'));
        $this->assertSame(['Hari ini', 'Pesanan', 'Trip & keberangkatan', 'Pembayaran', 'Halaman publik', 'Pengaturan'], array_column($nav, 'label'));
        $this->assertSame([
            url('/hari-ini'),
            url('/pesanan'),
            url('/trip'),
            url('/pesanan?status=perlu_tindakan'),
            url('/kencana'),
            url('/pengaturan'),
        ], array_column($nav, 'url'));
        $this->assertSame([false, false, false, false, true, false], array_column($nav, 'external'));
        $this->assertSame([true, false, false, false, false, false], array_column($nav, 'active'));
        $this->assertSame(['Rakyt', 'Booking'], [$this->todayProps($this->a)['seller']['brand'], $this->todayProps($this->a)['seller']['brand_suffix']]);
    }

    public function test_ac_2_the_current_section_is_marked_and_details_mark_pesanan(): void
    {
        $booking = $this->bookingFor($this->a, $this->departure);
        $active = fn (string $uri) => collect($this->actingAsTenant($this->a)->get($uri)->assertOk()->viewData('page')['props']['seller']['nav'])
            ->where('active', true)->pluck('key')->all();

        $this->assertSame(['bookings'], $active('/pesanan'));
        $this->assertSame(['bookings'], $active('/pesanan/'.$booking->public_id));
        $this->assertSame(['payments'], $active('/pesanan?status=perlu_tindakan'));
        $this->assertSame(['trips'], $active('/trip'));
        $this->assertSame(['trips'], $active('/keberangkatan'));
        $this->assertSame(['settings'], $active('/pengaturan'));
    }

    public function test_ac_2_ac_3_the_layout_marks_the_current_item_and_opens_the_public_page_in_a_new_tab(): void
    {
        $layout = file_get_contents(resource_path('js/layouts/SellerLayout.vue'));

        $this->assertStringContainsString(":aria-current=\"item.active ? 'page' : undefined\"", $layout);
        $this->assertStringContainsString("item.active ? 'bg-sogan font-semibold text-gading'", $layout);
        // The ink side nav carries the same "Rakyt | Booking" lockup as the landing nav.
        $this->assertMatchesRegularExpression('/<aside class="[^"]*\bbg-ink\b/', $layout);
        $this->assertMatchesRegularExpression('/<ProductMark[^>]*:suffix="seller\?\.brand_suffix"[^>]*tone="reverse"/s', $layout);
        $this->assertStringContainsString('<ProductMark', file_get_contents(resource_path('js/components/marketing/MarketingNav.vue')));
        $this->assertSame(['Rakyt', 'Booking'], [__('trips.nav.brand'), __('trips.nav.brand_suffix')]);
        $this->assertSame(__('landing.nav.brand_suffix'), __('trips.nav.brand_suffix'));
        $this->assertMatchesRegularExpression('/v-if="item\.external"[^>]*target="_blank"[^>]*rel="noopener"/s', $layout);
        // AC-3: the four phone items are at least 44 px tall.
        $this->assertStringContainsString('phoneNav', $layout);
        $this->assertMatchesRegularExpression('/v-for="item in phoneNav"[^>]*class="[^"]*min-h-14/s', $layout);
    }

    public function test_ac_3_the_phone_nav_has_four_items(): void
    {
        $nav = collect($this->todayProps($this->a)['seller']['nav'])->where('phone', true)->values();

        $this->assertSame(['today', 'bookings', 'trips', 'settings'], $nav->pluck('key')->all());
        $this->assertSame(['Hari ini', 'Pesanan', 'Trip', 'Pengaturan'], $nav->map(fn ($item) => $item['phone_label'] ?? $item['label'])->all());
    }

    public function test_ac_4_pesanan_and_pembayaran_show_the_perlu_tindakan_count(): void
    {
        $this->bookingFor($this->a, $this->departure, 1);
        $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview);
        $this->bookingFor($this->a, $this->departure, 1);
        // Not counted: an expired hold (scheduler late), paid and cancelled.
        $this->expiredHoldFor($this->a, $this->departure, 1);
        $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);
        $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Cancelled);

        $nav = collect($this->todayProps($this->a)['seller']['nav'])->keyBy('key');

        $this->assertSame(['count' => 3, 'label' => '3 pesanan perlu tindakan'], $nav['bookings']['badge']);
        $this->assertSame(['count' => 3, 'label' => '3 pesanan perlu tindakan'], $nav['payments']['badge']);
        $this->assertNull($nav['today']['badge']);
    }

    public function test_ac_4_no_badge_when_nothing_needs_action(): void
    {
        $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);

        $nav = collect($this->todayProps($this->a)['seller']['nav'])->keyBy('key');

        $this->assertNull($nav['bookings']['badge']);
        $this->assertNull($nav['payments']['badge']);
    }

    public function test_ac_4_the_count_is_one_query_and_never_runs_on_public_pages(): void
    {
        $this->bookingFor($this->a, $this->departure, 1);

        $queries = $this->asTenant($this->a, fn () => $this->queriesOf(fn () => DashboardPresenter::actionNeededCount()));
        $this->assertCount(1, $queries);

        // A signed-in seller on their own public page: no seller props, no booking query.
        $public = $this->queriesOf(fn () => $this->actingAsTenant($this->a)->get('/kencana')->assertOk());
        $this->assertEmpty(array_filter($public, fn (string $sql) => str_contains($sql, '"bookings"')));
        $this->assertArrayNotHasKey('seller', $this->sellerProps());
    }
}
