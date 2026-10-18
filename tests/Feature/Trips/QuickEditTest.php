<?php

namespace Tests\Feature\Trips;

use App\Models\TripDeparture;
use App\Trips\Contracts\DepartureBookings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-31–AC-34 (mockup 1g) and AC-42's quick-edit empty state.
 */
class QuickEditTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
    }

    public function test_ac_31_the_upcoming_list_offers_each_departure_for_quick_edit(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['name' => 'Karimunjawa']);
        $this->departureFor($a, $trip, '2024-10-31', seatsTaken: 5);
        $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 14);
        $this->departureFor($a, $trip, '2024-11-07', seatsTaken: 16);

        $this->actingAsTenant($a)->get(route('departures.upcoming'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Departures/Upcoming')
                ->has('departures', 3)
                ->where('departures.0.trip_name', 'Karimunjawa')
                ->where('departures.0.start_date', '2024-10-24')
                ->where('departures.0.end_date', '2024-10-26')
                ->where('departures.0.seats_taken', 14)
                ->where('departures.0.capacity', 16)
                ->where('departures.0.effective_price', 1_250_000)
                ->where('departures.0.urls.quick', route('trips.departures.quick', [$trip, TripDeparture::acrossOrganizations()->whereDate('start_date', '2024-10-24')->sole()]))
                ->where('departures.2.display_status', 'full')
                ->where('copy.quick_edit.title', 'Edit cepat'));
    }

    public function test_ac_32_a_different_price_becomes_the_override_and_the_same_price_changes_nothing(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, ['base_price' => 1_250_000]);
        $departure = $this->departureFor($a, $trip, '2024-10-24');
        $quick = route('trips.departures.quick', [$trip, $departure]);

        $this->actingAsTenant($a)->patch($quick, ['price' => 1_250_000, 'capacity' => 16, 'status' => 'open'])
            ->assertSessionHas('toast', 'Tersimpan');
        $this->assertNull($this->freshDeparture($departure)->price_override);

        $this->actingAsTenant($a)->patch($quick, ['price' => 1_350_000, 'capacity' => 16, 'status' => 'open']);
        $this->assertSame(1_350_000, $this->freshDeparture($departure)->price_override);
    }

    public function test_ac_32_the_sheet_knows_how_many_bookings_keep_their_price(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 14);
        $this->app->instance(DepartureBookings::class, new class implements DepartureBookings
        {
            public function hasAnyBooking(TripDeparture $departure): bool
            {
                return true;
            }

            public function activeBookingCount(TripDeparture $departure): int
            {
                return 14;
            }
        });

        $this->actingAsTenant($a)->get(route('departures.upcoming'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.0.active_bookings', 14)
                ->where('copy.quick_edit.price_note', 'Harga baru hanya berlaku untuk pesanan berikutnya.')
                ->where('copy.quick_edit.price_note_bookings', ':n pesanan yang ada tidak berubah.'));
    }

    public function test_ac_33_seats_cannot_go_below_the_seats_taken(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 14);
        $quick = route('trips.departures.quick', [$trip, $departure]);

        $this->actingAsTenant($a)->patchJson($quick, ['price' => 1_250_000, 'capacity' => 13, 'status' => 'open'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity' => '14 terisi — tidak bisa di bawah 14']);

        $this->actingAsTenant($a)->patchJson($quick, ['price' => 1_250_000, 'capacity' => 14, 'status' => 'open']);
        $this->assertSame(14, $this->freshDeparture($departure)->capacity);
    }

    public function test_ac_33_a_stale_sheet_is_rejected_with_the_current_number_and_nothing_is_saved(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24', seatsTaken: 10);

        // The sheet opened at 10 taken; two more seats were booked since (F-005).
        $this->asTenant($a, fn () => TripDeparture::query()->whereKey($departure->id)->increment('seats_taken', 2));

        $this->actingAsTenant($a)->from(route('departures.upcoming'))
            ->patch(route('trips.departures.quick', [$trip, $departure]), ['price' => 1_400_000, 'capacity' => 11, 'status' => 'closed'])
            ->assertRedirect(route('departures.upcoming'))
            ->assertSessionHasErrors(['capacity' => '12 terisi — tidak bisa di bawah 12']);

        $fresh = $this->freshDeparture($departure);
        $this->assertSame([16, null, 'open'], [$fresh->capacity, $fresh->price_override, $fresh->status->value]);

        $this->actingAsTenant($a)->get(route('departures.upcoming'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('departures.0.seats_taken', 12));
    }

    public function test_ac_34_reopening_after_the_deadline_needs_a_new_future_deadline(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-12', [
            'booking_deadline_preset' => 'h3',
            'booking_deadline_at' => '2024-10-09 16:59:59',
            'status' => 'closed',
            'closed_reason' => 'deadline',
        ]);
        $quick = route('trips.departures.quick', [$trip, $departure]);

        $this->actingAsTenant($a)->patchJson($quick, ['price' => 1_250_000, 'capacity' => 16, 'status' => 'open'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_deadline_preset' => 'Tutup pesan sudah lewat (9 Okt 23.59). Pilih tanggal tutup baru untuk membuka lagi.']);

        $this->actingAsTenant($a)->patchJson($quick, ['price' => 1_250_000, 'capacity' => 16, 'status' => 'open', 'booking_deadline_preset' => 'h3'])
            ->assertUnprocessable();
        $this->assertSame('closed', $this->freshDeparture($departure)->status->value);

        $this->actingAsTenant($a)->patchJson($quick, ['price' => 1_250_000, 'capacity' => 16, 'status' => 'open', 'booking_deadline_preset' => 'h1'])
            ->assertRedirect();

        $fresh = $this->freshDeparture($departure);
        $this->assertSame('open', $fresh->status->value);
        $this->assertNull($fresh->closed_reason);
        $this->assertSame('2024-10-11 23:59', $fresh->booking_deadline_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_ac_34_the_sheet_receives_the_passed_deadline(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->departureFor($a, $trip, '2024-10-12', ['booking_deadline_at' => '2024-10-09 16:59:59', 'status' => 'closed', 'closed_reason' => 'deadline']);

        $this->actingAsTenant($a)->get(route('departures.upcoming'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.0.deadline_passed', true)
                ->where('departures.0.deadline_short', '9 Okt 23.59'));
    }

    public function test_quick_edit_can_close_a_departure(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $departure = $this->departureFor($a, $trip, '2024-10-24');

        $this->actingAsTenant($a)->patchJson(route('trips.departures.quick', [$trip, $departure]), ['price' => 1_250_000, 'capacity' => 18, 'status' => 'closed'])->assertRedirect();

        $fresh = $this->freshDeparture($departure);
        $this->assertSame(['closed', 'manual', 18], [$fresh->status->value, $fresh->closed_reason->value, $fresh->capacity]);
    }

    public function test_the_upcoming_list_shows_20_nearest_first_and_loads_more_on_request(): void
    {
        [$a, $b] = $this->createTenants();
        $trip = $this->tripFor($a);
        $start = CarbonImmutable::parse('2024-10-11');

        foreach (range(24, 0) as $day) {
            $this->departureFor($a, $trip, $start->addDays($day)->toDateString());
        }
        $this->departureFor($b, $this->tripFor($b), '2024-10-11');

        $first = null;
        $this->actingAsTenant($a)->get(route('departures.upcoming'))
            ->assertInertia(function (AssertableInertia $page) use (&$first) {
                $page->has('departures', 20)
                    ->where('departures.0.start_date', '2024-10-11')
                    ->where('departures.19.start_date', '2024-10-30')
                    ->where('pagination.page', 1)
                    ->where('copy.upcoming.more', 'Tampilkan lebih banyak');
                $first = $page->toArray()['props']['pagination']['next_url'];
            });

        $this->assertNotNull($first);

        $this->actingAsTenant($a)->get($first)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('departures', 5)
                ->where('departures.0.start_date', '2024-10-31')
                ->where('departures.4.start_date', '2024-11-04')
                ->where('pagination.page', 2)
                ->where('pagination.next_url', null));

        // Seller B sees only their own departure.
        $this->actingAsTenant($b)->get(route('departures.upcoming'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('departures', 1)->where('pagination.next_url', null));
    }

    public function test_ac_42_no_upcoming_departures_shows_the_empty_state(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->get(route('departures.upcoming'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('departures', 0)
                ->where('copy.upcoming.empty', 'Belum ada keberangkatan terdekat'));
    }
}
