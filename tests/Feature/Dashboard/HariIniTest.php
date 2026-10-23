<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingStatus;
use App\Dashboard\DashboardPresenter;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\DepartureStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-007 section B: "Hari ini" (mockups 1e/2f), AC-5–AC-15. The clock starts
 * at Thursday 2024-10-10 10:00 WIB.
 */
class HariIniTest extends TestCase
{
    use BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->a->owner->forceFill(['name' => 'Agus'])->save();
        $this->trip = $this->bookableTrip($this->a, ['name' => 'Karimunjawa 3H2M Open Trip']);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    public function test_ac_5_header_greeting_date_business_plan_and_new_trip(): void
    {
        $this->freezeClock('2026-10-16 08:15');

        $props = $this->todayProps($this->a);

        $this->assertSame(['text' => 'Selamat pagi, Agus.', 'date' => 'Jumat, 16 Oktober'], $props['greeting']);
        $this->assertSame(['name' => 'Kencana Trip', 'slug' => 'kencana', 'plan_label' => 'Paket Gratis'], $props['organization']);
        $this->assertSame(url('/trip/baru'), $props['urls']['new_trip']);
        $this->assertSame('+ Trip baru', $props['copy']['new_trip']);
    }

    public function test_ac_5_the_greeting_follows_the_local_hour_boundaries(): void
    {
        $expected = [0 => 'malam', 3 => 'malam', 4 => 'pagi', 10 => 'pagi', 11 => 'siang', 14 => 'siang', 15 => 'sore', 17 => 'sore', 18 => 'malam', 23 => 'malam'];

        foreach ($expected as $hour => $period) {
            $this->assertSame($period, DashboardPresenter::period($hour), "{$hour}.00");
        }

        // 10.00 WIB is 12.00 in Jayapura (WIT): siang there, by the organization's zone.
        $this->a->organization->forceFill(['timezone' => 'Asia/Jayapura'])->save();
        $this->assertSame('Selamat siang, Agus.', $this->todayProps($this->a)['greeting']['text']);
        $this->freezeClock('2024-10-10 17:59');
        $this->assertSame('Selamat malam, Agus.', $this->todayProps($this->a)['greeting']['text']);
    }

    public function test_ac_5_new_trip_is_the_pages_one_gold_button(): void
    {
        $page = file_get_contents(resource_path('js/pages/Dashboard/Today.vue'));

        $this->assertSame(1, substr_count($page, 'bg-prada'));
        $this->assertMatchesRegularExpression('/:href="urls\.new_trip"\s+class="[^"]*bg-prada/', $page);
        $this->assertSame(0, substr_count(file_get_contents(resource_path('js/components/LinkCard.vue')), 'bg-prada'));
        $this->assertSame(0, substr_count(file_get_contents(resource_path('js/components/BookingRow.vue')), 'bg-prada'));
    }

    public function test_ac_6_sections_stack_tasks_first_on_a_phone(): void
    {
        $page = file_get_contents(resource_path('js/pages/Dashboard/Today.vue'));
        preg_match_all('/data-section="([a-z_]+)"/', $page, $matches);

        $this->assertSame(['header', 'action_needed', 'departures', 'recent', 'link'], $matches[1]);
        // Desktop: two columns (1e/2f).
        $this->assertStringContainsString('lg:grid-cols-', $page);
    }

    public function test_ac_7_perlu_tindakan_rows_carry_their_fields_and_link_to_the_detail(): void
    {
        $waiting = $this->bookingFor($this->a, $this->departure, 2, attributes: ['customer_name' => 'Rina Wulandari', 'reference' => 'RKB-KCN-1024-0142']);
        $review = $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview);

        $items = collect($this->todayProps($this->a)['action_needed']['items'])->keyBy('reference');
        $row = $items['RKB-KCN-1024-0142'];

        $this->assertSame('Rina Wulandari', $row['customer_name']);
        $this->assertSame(2, $row['participants']);
        $this->assertSame('awaiting_payment', $row['status']);
        $this->assertSame(['name' => 'Karimunjawa 3H2M Open Trip'], $row['trip']);
        $this->assertSame(['start_date' => '2024-10-24', 'end_date' => '2024-10-26'], $row['departure']);
        $this->assertSame(2_500_000, $row['total_amount']);
        $this->assertSame('2024-10-10T05:00:00Z', $row['hold_expires_at']);
        $this->assertSame(url('/pesanan/'.$waiting->public_id), $row['urls']['show']);
        // Ingatkan via WA goes through the seller route (ADR-0029 §1); none for needs_review (F-006 adds its actions).
        $this->assertSame(url('/pesanan/'.$waiting->public_id.'/ingatkan'), $row['whatsapp_reminder_url']);
        $this->assertNull($items[$review->reference]['whatsapp_reminder_url']);
    }

    public function test_ac_8_needs_review_first_then_active_holds_soonest_first_at_most_five(): void
    {
        $now = CarbonImmutable::now();
        $later = $this->bookingFor($this->a, $this->departure, 1, attributes: ['hold_expires_at' => $now->addMinutes(100)]);
        $soon = $this->bookingFor($this->a, $this->departure, 1, attributes: ['hold_expires_at' => $now->addMinutes(10)]);
        $reviewNew = $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview, $now->subMinutes(5));
        $reviewOld = $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview, $now->subHour());
        $this->expiredHoldFor($this->a, $this->departure, 1);
        $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);
        $more = [];
        foreach ([110, 115, 118] as $minutes) {
            $more[] = $this->bookingFor($this->a, $this->departure, 1, attributes: ['hold_expires_at' => $now->addMinutes($minutes)]);
        }

        $section = $this->todayProps($this->a)['action_needed'];

        $this->assertSame(7, $section['count']);
        $this->assertSame([$reviewOld->reference, $reviewNew->reference, $soon->reference, $later->reference, $more[0]->reference], $this->references($section['items']));
        $this->assertSame(url('/pesanan?status=perlu_tindakan'), $this->todayProps($this->a)['urls']['action_needed']);
        $this->assertSame('Perlu tindakan · :n pembayaran', $this->todayProps($this->a)['copy']['action_needed']['title']);
    }

    public function test_ac_8_an_expired_unreleased_hold_is_neither_listed_nor_counted(): void
    {
        $expired = $this->expiredHoldFor($this->a, $this->departure, 2);

        $section = $this->todayProps($this->a)['action_needed'];

        $this->assertSame(0, $section['count']);
        $this->assertNotContains($expired->reference, $this->references($section['items']));
    }

    public function test_ac_9_nothing_to_do_shows_the_all_clear(): void
    {
        $this->bookingFor($this->a, $this->departure, 2, BookingStatus::Paid);

        $props = $this->todayProps($this->a);

        $this->assertSame(['count' => 0, 'items' => []], $props['action_needed']);
        $this->assertSame('Semua beres. Tidak ada pembayaran yang perlu ditindak.', $props['copy']['action_needed']['empty']);
        $this->assertMatchesRegularExpression('/v-if="action_needed\.count > 0"[^>]*:href="urls\.action_needed"/s', file_get_contents(resource_path('js/pages/Dashboard/Today.vue')));
    }

    public function test_ac_10_recent_lists_the_five_newest_bookings_in_any_status(): void
    {
        $now = CarbonImmutable::now();
        $refs = [];
        foreach ([BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::AwaitingPayment, BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::AwaitingPayment] as $i => $status) {
            $refs[] = $this->bookingFor($this->a, $this->departure, 1, $status, ['created_at' => $now->subDays(6 - $i)])->reference;
        }

        $props = $this->todayProps($this->a);

        $this->assertSame(array_reverse(array_slice($refs, 1)), $this->references($props['recent']));
        $this->assertSame(['awaiting_payment', 'cancelled', 'paid', 'awaiting_payment', 'cancelled'], array_column($props['recent'], 'status'));
        $this->assertSame('2024-10-08T03:00:00Z', $props['recent'][1]['created_at']);
        $this->assertSame(url('/pesanan'), $props['urls']['bookings']);
        $this->assertSame('Belum ada pesanan. Bagikan link booking Anda supaya pelanggan bisa memesan.', $props['copy']['recent']['empty']);
    }

    public function test_ac_10_no_bookings_yet(): void
    {
        $this->assertSame([], $this->todayProps($this->a)['recent']);
    }

    public function test_ac_11_the_next_three_departures_in_any_state_nearest_first(): void
    {
        $other = $this->tripFor($this->a, ['name' => 'Bromo Sunrise']);
        $this->departureFor($this->a, $this->trip, '2024-10-09');
        $today = $this->departureFor($this->a, $other, '2024-10-10', ['status' => DepartureStatus::Closed, 'closed_reason' => 'manual']);
        $bromo = $this->departureFor($this->a, $other, '2024-10-24');
        $this->departureFor($this->a, $this->trip, '2024-11-01');

        $props = $this->todayProps($this->a);
        $rows = $props['departures'];

        $this->assertSame([$today->public_id, $bromo->public_id, $this->departure->public_id], array_column($rows, 'id'));
        $this->assertSame(['Bromo Sunrise', 'Bromo Sunrise', 'Karimunjawa 3H2M Open Trip'], array_column(array_column($rows, 'trip'), 'name'));
        $this->assertSame('closed', $rows[0]['display_status']);
        $this->assertSame(url('/keberangkatan'), $props['urls']['departures']);
    }

    public function test_ac_11_no_upcoming_departure(): void
    {
        TripDeparture::acrossOrganizations()->whereKey($this->departure->id)->update(['start_date' => '2024-10-01', 'end_date' => '2024-10-03']);

        $props = $this->todayProps($this->a);

        $this->assertSame([], $props['departures']);
        $this->assertSame('Belum ada keberangkatan terjadwal.', $props['copy']['departures']['empty']);
        $this->assertSame(url('/trip'), $props['urls']['trips']);
    }

    public function test_ac_12_the_seat_bar_splits_paid_held_and_empty(): void
    {
        $this->bookingFor($this->a, $this->departure, 12, BookingStatus::Paid);
        $this->bookingFor($this->a, $this->departure, 1);
        $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview);
        $this->bookingFor($this->a, $this->departure, 3, BookingStatus::Cancelled);

        $row = $this->todayProps($this->a)['departures'][0];

        $this->assertSame(['capacity' => 16, 'paid' => 12, 'held' => 2, 'empty' => 2], $row['seats']);
        $this->assertSame($row['seats_taken'], $row['seats']['paid'] + $row['seats']['held']);
        $this->assertSame($this->freshDeparture($this->departure)->seats_taken, $row['seats']['paid'] + $row['seats']['held']);
        // Open, before its deadline (Senin 21 Okt 23.59 WIB).
        $this->assertSame('2024-10-21', $row['closes_on']);
    }

    public function test_ac_12_an_expired_unreleased_hold_still_counts_as_held(): void
    {
        $this->expiredHoldFor($this->a, $this->departure, 2);

        $this->assertSame(['capacity' => 16, 'paid' => 0, 'held' => 2, 'empty' => 14], $this->todayProps($this->a)['departures'][0]['seats']);
    }

    public function test_ac_12_no_closing_date_once_closed_or_past_the_deadline(): void
    {
        $closed = $this->departureFor($this->a, $this->trip, '2024-10-12', ['status' => DepartureStatus::Closed, 'closed_reason' => 'manual']);
        $deadlinePassed = $this->departureFor($this->a, $this->trip, '2024-10-11');

        $rows = collect($this->todayProps($this->a)['departures'])->keyBy('id');

        $this->assertNull($rows[$closed->public_id]['closes_on']);
        $this->assertNull($rows[$deadlinePassed->public_id]['closes_on']);
        $this->assertSame('2024-10-21', $rows[$this->departure->public_id]['closes_on']);
    }

    public function test_ac_12_ac_38_the_bar_is_hidden_from_screen_readers_and_the_text_always_shown(): void
    {
        $bar = file_get_contents(resource_path('js/components/SeatBar.vue'));

        $this->assertMatchesRegularExpression('/aria-hidden="true"/', $bar);
        $this->assertStringContainsString('seatSummaryParts(', $bar);
        // Mockup 2f: solid status colours from tokens, shared by the bar and the legend.
        $this->assertStringContainsString('SEAT_SEGMENT_CLASSES[segment.key]', $bar);
        $this->assertStringContainsString('SEAT_SEGMENT_CLASSES[item.key]', file_get_contents(resource_path('js/pages/Dashboard/Today.vue')));
        $lib = file_get_contents(resource_path('js/lib/dashboard.js'));
        foreach (["paid: 'bg-status-paid'", "held: 'bg-status-waiting-bar'", "empty: 'bg-ink/12'"] as $class) {
            $this->assertStringContainsString($class, $lib);
        }
    }

    public function test_ac_13_edit_cepat_opens_f003s_sheet_and_returns_to_hari_ini(): void
    {
        $this->bookingFor($this->a, $this->departure, 3);
        $row = $this->todayProps($this->a)['departures'][0];

        foreach (['effective_price', 'capacity', 'status', 'seats_taken', 'deadline_passed', 'deadline_short', 'trip_name', 'active_bookings'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }

        $this->actingAsTenant($this->a)->from('/hari-ini')
            ->patch($row['urls']['quick'], ['price' => 1_400_000, 'capacity' => 2, 'status' => 'open'])
            ->assertRedirect('/hari-ini')
            ->assertSessionHasErrors('capacity');

        $this->actingAsTenant($this->a)->from('/hari-ini')
            ->patch($row['urls']['quick'], ['price' => 1_400_000, 'capacity' => 20, 'status' => 'open'])
            ->assertRedirect('/hari-ini')
            ->assertSessionHas('toast', __('trips.notices.saved'));

        $updated = $this->todayProps($this->a)['departures'][0];
        $this->assertSame([20, 1_400_000, 17], [$updated['capacity'], $updated['effective_price'], $updated['seats']['empty']]);

        $page = file_get_contents(resource_path('js/pages/Dashboard/Today.vue'));
        $this->assertStringContainsString('<QuickEditSheet', $page);
    }

    public function test_ac_14_the_link_card_shows_the_public_link(): void
    {
        $props = $this->todayProps($this->a);

        $this->assertSame('https://rakyt.id/kencana', $props['public_url']);
        $this->assertSame(url('/kencana'), $props['urls']['public_page']);
        $this->assertSame(['title' => 'Link booking Anda', 'copy' => 'Salin link', 'share' => '↗ Bagikan ke WA / IG', 'copied' => 'Link disalin'], array_intersect_key($props['copy']['link'], array_flip(['title', 'copy', 'share', 'copied'])));

        $card = file_get_contents(resource_path('js/components/LinkCard.vue'));
        // F-001 D5's behaviour, reused: native share, else copy.
        $this->assertStringContainsString("import { copyLink, shareOrCopy } from '../lib/onboarding.js'", $card);
        $this->assertMatchesRegularExpression('/:href="viewUrl"\s+target="_blank"\s+rel="noopener"/', $card);
        $this->assertStringContainsString('border-2 border-ink', $card);
    }

    public function test_ac_15_the_query_count_does_not_grow_with_bookings(): void
    {
        $second = $this->departureFor($this->a, $this->trip, '2024-10-31');
        $third = $this->departureFor($this->a, $this->trip, '2024-11-07');
        $departures = [$this->departure, $second, $third];

        foreach ($departures as $departure) {
            $this->bookingFor($this->a, $departure, 1);
        }

        $small = count($this->queriesOf(fn () => $this->todayProps($this->a)));

        for ($i = 0; $i < 27; $i++) {
            $departure = $departures[$i % 3];
            $status = [BookingStatus::AwaitingPayment, BookingStatus::Paid, BookingStatus::Cancelled][$i % 3];
            $this->bookingFor($this->a, $departure, 1, $status);
        }

        $large = count($this->queriesOf(fn () => $this->todayProps($this->a)));

        $this->assertSame($small, $large);
    }
}
