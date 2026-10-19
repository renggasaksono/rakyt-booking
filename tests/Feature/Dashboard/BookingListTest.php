<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingFilters;
use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
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
 * F-007 section C: the booking list `/pesanan`, AC-16–AC-22. The clock starts
 * at Thursday 2024-10-10 10:00 WIB.
 */
class BookingListTest extends TestCase
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
        $this->trip = $this->bookableTrip($this->a, ['name' => 'Karimunjawa 3H2M']);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24', ['capacity' => 999]);
    }

    private function book(string $reference, array $attributes = [], BookingStatus $status = BookingStatus::AwaitingPayment, ?TripDeparture $departure = null, int $participants = 1): Booking
    {
        return $this->bookingFor($this->a, $departure ?? $this->departure, $participants, $status, ['reference' => $reference, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function found(array $query): array
    {
        return $this->references($this->listProps($this->a, $query)['bookings']);
    }

    public function test_ac_16_rows_newest_first_with_their_fields(): void
    {
        $now = CarbonImmutable::now();
        $old = $this->book('RKB-KCN-1024-0001', ['created_at' => $now->subDay()]);
        $new = $this->book('RKB-KCN-1024-0002', ['customer_name' => 'Rina Wulandari'], participants: 2);
        $same = $this->book('RKB-KCN-1024-0003', ['created_at' => $now->subDay()]);

        $props = $this->listProps($this->a);
        $row = $props['bookings'][0];

        $this->assertSame([$new->reference, $same->reference, $old->reference], $this->references($props['bookings']));
        $this->assertSame([
            'public_id', 'reference', 'customer_name', 'participants', 'total_amount', 'status', 'hold_expires_at',
            'created_at', 'trip', 'departure', 'urls',
        ], array_keys($row));
        $this->assertSame(['Rina Wulandari', 2, 2_500_000, 'awaiting_payment'], [$row['customer_name'], $row['participants'], $row['total_amount'], $row['status']]);
        $this->assertSame(['start_date' => '2024-10-24', 'end_date' => '2024-10-26'], $row['departure']);
        $this->assertSame(url('/pesanan/'.$new->public_id), $row['urls']['show']);
        $this->assertSame(':n org', $props['copy']['index']['participants']);
    }

    public function test_ac_16_twenty_per_page_with_next_and_previous(): void
    {
        $now = CarbonImmutable::now();
        for ($i = 1; $i <= 45; $i++) {
            $this->book(sprintf('RKB-KCN-1024-%04d', $i), ['created_at' => $now->subMinutes(100 - $i)]);
        }

        $first = $this->listProps($this->a);
        $this->assertCount(20, $first['bookings']);
        $this->assertSame('RKB-KCN-1024-0045', $first['bookings'][0]['reference']);
        $this->assertSame(['page' => 1, 'next_url' => url('/pesanan?halaman=2'), 'prev_url' => null], $first['pagination']);

        $third = $this->listProps($this->a, ['halaman' => 3]);
        $this->assertSame(['RKB-KCN-1024-0005', 'RKB-KCN-1024-0004', 'RKB-KCN-1024-0003', 'RKB-KCN-1024-0002', 'RKB-KCN-1024-0001'], $this->references($third['bookings']));
        $this->assertSame(['page' => 3, 'next_url' => null, 'prev_url' => url('/pesanan?halaman=2')], $third['pagination']);

        $this->assertSame(url('/pesanan'), $this->listProps($this->a, ['halaman' => 2])['pagination']['prev_url']);
    }

    public function test_ac_17_search_by_name_phone_or_reference(): void
    {
        $rina = $this->book('RKB-BRM-1024-0142', ['customer_name' => 'Rina Wulandari', 'customer_phone' => '+6281234567890']);
        $budi = $this->book('RKB-BRM-1024-0150', ['customer_name' => 'Budi Santoso', 'customer_phone' => '+6285711112222']);

        foreach (['rina', 'RINA wul', '  rina  ', '0812 3456 7890', '+62 812-3456-7890', '6281234567890', '7890', 'rkb-brm-1024-0142', '0142'] as $term) {
            $this->assertSame([$rina->reference], $this->found(['cari' => $term]), "[{$term}]");
        }

        $this->assertSame([$budi->reference], $this->found(['cari' => '0857-1111-2222']));
        $this->assertSame([$budi->reference, $rina->reference], $this->found(['cari' => 'rkb-brm']));
    }

    public function test_ac_17_wildcards_are_text_and_short_terms_are_ignored(): void
    {
        $percent = $this->book('RKB-KCN-1024-0001', ['customer_name' => '100% Wisata']);
        $underscore = $this->book('RKB-KCN-1024-0002', ['customer_name' => 'Tour_Bali']);
        $this->book('RKB-KCN-1024-0003', ['customer_name' => 'Tour Bali']);

        $this->assertSame([$percent->reference], $this->found(['cari' => '0%']));
        $this->assertSame([$underscore->reference], $this->found(['cari' => 'r_b']));
        $this->assertSame([], $this->found(['cari' => '%%']));
        $this->assertCount(3, $this->found(['cari' => 'r']));
        $this->assertCount(3, $this->found(['cari' => '   ']));
        $this->assertNull($this->listProps($this->a, ['cari' => ' a '])['filters']['cari']);
        $this->assertSame([], $this->found(['cari' => "O'Brien 😀"]));
    }

    public function test_ac_17_phone_digits_follow_phone_number_prefixes(): void
    {
        $this->assertSame('81234567890', BookingFilters::phoneDigits('0812 3456 7890'));
        $this->assertSame('81234567890', BookingFilters::phoneDigits('+62 812-3456-7890'));
        $this->assertSame('81234567890', BookingFilters::phoneDigits('0062 812 3456 7890'));
        $this->assertSame('7890', BookingFilters::phoneDigits('7890'));
        $this->assertNull(BookingFilters::phoneDigits('789'));
        $this->assertNull(BookingFilters::phoneDigits('rina 7890'));
    }

    public function test_ac_18_each_filter_and_their_combination(): void
    {
        $bromo = $this->tripFor($this->a, ['name' => 'Bromo Sunrise']);
        $bromoDeparture = $this->departureFor($this->a, $bromo, '2024-11-02');
        $waiting = $this->book('RKB-KCN-1024-0001');
        $review = $this->setStatus($this->book('RKB-KCN-1024-0002'), BookingStatus::NeedsReview);
        $paid = $this->book('RKB-KCN-1102-0003', [], BookingStatus::Paid, $bromoDeparture);
        $rejected = $this->book('RKB-KCN-1102-0004', [], BookingStatus::Rejected, $bromoDeparture);
        $cancelled = $this->book('RKB-KCN-1102-0005', ['customer_name' => 'Budi'], BookingStatus::Cancelled, $bromoDeparture);
        $expired = $this->expiredHoldFor($this->a, $this->departure, 1);

        $this->assertEqualsCanonicalizing([$waiting->reference, $review->reference], $this->found(['status' => 'perlu_tindakan']));
        $this->assertEqualsCanonicalizing([$waiting->reference, $expired->reference], $this->found(['status' => 'awaiting_payment']));
        $this->assertSame([$review->reference], $this->found(['status' => 'needs_review']));
        $this->assertSame([$paid->reference], $this->found(['status' => 'paid']));
        $this->assertSame([$rejected->reference], $this->found(['status' => 'rejected']));
        $this->assertSame([$cancelled->reference], $this->found(['status' => 'cancelled']));
        $this->assertEqualsCanonicalizing([$paid->reference, $rejected->reference, $cancelled->reference], $this->found(['trip' => $bromo->public_id]));
        $this->assertEqualsCanonicalizing([$paid->reference, $rejected->reference, $cancelled->reference], $this->found(['dari' => '2024-11-02', 'sampai' => '2024-11-02']));
        $this->assertCount(3, $this->found(['sampai' => '2024-10-24']));
        $this->assertCount(3, $this->found(['dari' => '2024-10-25']));
        $this->assertCount(3, $this->found(['keberangkatan' => $this->departure->public_id]));
        $this->assertSame([$cancelled->reference], $this->found(['trip' => $bromo->public_id, 'status' => 'cancelled', 'cari' => 'budi']));
        $this->assertSame([], $this->found(['trip' => $bromo->public_id, 'keberangkatan' => $this->departure->public_id]));

        $props = $this->listProps($this->a, ['keberangkatan' => $bromoDeparture->public_id]);
        $this->assertSame(['trip_name' => 'Bromo Sunrise', 'start_date' => '2024-11-02'], $props['options']['departure']);
        $this->assertSame([['id' => $bromo->public_id, 'name' => 'Bromo Sunrise'], ['id' => $this->trip->public_id, 'name' => 'Karimunjawa 3H2M']], $props['options']['trips']);
        $this->assertSame(['perlu_tindakan', 'awaiting_payment', 'needs_review', 'paid', 'rejected', 'cancelled'], array_column($props['options']['statuses'], 'value'));
        $this->assertSame(['Perlu tindakan', 'Menunggu pembayaran', 'Perlu dicek', 'Lunas', 'Ditolak', 'Dibatalkan'], array_column($props['options']['statuses'], 'label'));
    }

    public function test_ac_19_filters_live_in_the_url_and_carry_into_links(): void
    {
        $now = CarbonImmutable::now();
        for ($i = 1; $i <= 21; $i++) {
            $this->book(sprintf('RKB-KCN-1024-%04d', $i), ['customer_name' => 'Rina', 'created_at' => $now->subMinutes(100 - $i)]);
        }
        $query = ['cari' => 'rina', 'status' => 'awaiting_payment', 'trip' => $this->trip->public_id, 'dari' => '2024-10-01', 'sampai' => '2024-10-31', 'keberangkatan' => $this->departure->public_id];

        $props = $this->listProps($this->a, $query);

        $this->assertSame($query, $props['filters']);
        $this->assertSame(url('/pesanan?'.http_build_query([...$query, 'halaman' => 2])), $props['pagination']['next_url']);
        $first = Booking::acrossOrganizations()->where('reference', 'RKB-KCN-1024-0021')->sole();
        $this->assertSame(url('/pesanan/'.$first->public_id.'?'.http_build_query($query)), $props['bookings'][0]['urls']['show']);

        // The detail's "←" goes back to the same list.
        $detail = $this->propsOf($this->actingAsTenant($this->a)->get($props['bookings'][0]['urls']['show']), 'Bookings/Show');
        $this->assertSame(url('/pesanan?'.http_build_query($query)), $detail['urls']['back']);

        $index = file_get_contents(resource_path('js/pages/Bookings/Index.vue'));
        $this->assertStringContainsString('filtersToQuery(', $index);
        $this->assertStringContainsString("t(copy, 'index.clear')", $index);
    }

    public function test_ac_20_invalid_filters_are_dropped_and_the_list_renders(): void
    {
        $this->book('RKB-KCN-1024-0001');
        $b = $this->seller('bahari', 'Bahari');
        $bTrip = $this->bookableTrip($b);
        $bDeparture = $this->departureFor($b, $bTrip, '2024-10-24');

        $cases = [
            ['status' => 'completed'],
            ['status' => ['paid']],
            ['dari' => '2024-13-01'],
            ['sampai' => '24-10-2024'],
            ['dari' => '2024-10-31', 'sampai' => '2024-10-01'],
            ['trip' => '01jabcdefghjkmnpqrstvwxyz0'],
            ['trip' => $bTrip->public_id],
            ['trip' => (string) $this->trip->id],
            ['keberangkatan' => $bDeparture->public_id],
            ['keberangkatan' => "x\0y"],
            ['halaman' => 'dua'],
            ['halaman' => '-1'],
            ['cari' => str_repeat('a', 101)],
        ];

        foreach ($cases as $query) {
            $props = $this->listProps($this->a, $query);
            $this->assertSame(['RKB-KCN-1024-0001'], $this->references($props['bookings']), json_encode($query));
            $this->assertSame(['cari' => null, 'status' => null, 'trip' => null, 'dari' => null, 'sampai' => null, 'keberangkatan' => null], $props['filters'], json_encode($query));
        }

        // A page past the end is just empty.
        $this->assertSame([], $this->listProps($this->a, ['halaman' => 999999])['bookings']);
    }

    public function test_ac_21_empty_states(): void
    {
        $none = $this->listProps($this->a);
        $this->assertSame([[], false], [$none['bookings'], $none['has_any']]);
        $this->assertSame('Belum ada pesanan.', $none['copy']['index']['empty']);
        $this->assertSame('https://rakyt.id/kencana', $none['urls']['public_url']);

        $this->book('RKB-KCN-1024-0001');
        $noMatch = $this->listProps($this->a, ['cari' => 'tidak ada']);
        $this->assertSame([[], true], [$noMatch['bookings'], $noMatch['has_any']]);
        $this->assertSame('Tidak ada pesanan yang cocok.', $noMatch['copy']['index']['no_match']);

        $index = file_get_contents(resource_path('js/pages/Bookings/Index.vue'));
        $this->assertStringContainsString('<LinkCard', $index);
    }

    public function test_ac_22_phone_filter_sheet_and_chips(): void
    {
        $index = file_get_contents(resource_path('js/pages/Bookings/Index.vue'));

        $this->assertStringContainsString('<BottomSheet', $index);
        $this->assertStringContainsString("t(copy, 'index.filter_count', { n: activeCount })", $index);
        $this->assertStringContainsString("t(copy, 'index.apply')", $index);
        $this->assertStringContainsString('chips', $index);
        // The sheet's "Terapkan" is ink, not gold.
        $this->assertSame(0, substr_count($index, 'bg-prada'));
    }
}
