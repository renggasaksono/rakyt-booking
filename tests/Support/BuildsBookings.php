<?php

namespace Tests\Support;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\After;

/**
 * Fixtures for F-005. Use with InteractsWithTenants, BuildsTrips and
 * BuildsPublicPages; the clock is frozen at Thursday 2024-10-10 10:00 WIB.
 *
 * Seats are only ever taken by bookings here, so the seat invariant
 * (AC-23) is checked after every test of a class that uses this trait.
 */
trait BuildsBookings
{
    /**
     * Published, scheduled `karimunjawa-3h2m`: Rp1.250.000, Pelabuhan Jepara 06.00.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function bookableTrip(Tenant $tenant, array $attributes = []): Trip
    {
        return $this->tripFor($tenant, ['name' => 'Karimunjawa 3H2M', ...$attributes], published: true);
    }

    /**
     * A booking whose seats are taken like BookingService takes them (holding statuses only).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function bookingFor(Tenant $tenant, TripDeparture $departure, int $participants = 2, BookingStatus $status = BookingStatus::AwaitingPayment, array $attributes = []): Booking
    {
        return $this->asTenant($tenant, function () use ($departure, $participants, $status, $attributes) {
            $price = $attributes['unit_price'] ?? 1_250_000;
            $booking = Booking::factory()->create([
                'trip_departure_id' => $departure->id,
                'participants' => $participants,
                'unit_price' => $price,
                'total_amount' => $participants * $price,
                'status' => $status,
                'cancel_reason' => $status === BookingStatus::Cancelled ? 'hold_expired' : null,
                ...$attributes,
            ]);

            if ($status->holdsSeats()) {
                TripDeparture::query()->whereKey($departure->id)->increment('seats_taken', $participants);
            }

            return $booking;
        });
    }

    /**
     * An awaiting_payment booking whose hold ended at $expiredAt and was not released yet.
     */
    protected function expiredHoldFor(Tenant $tenant, TripDeparture $departure, int $participants = 2, ?CarbonImmutable $expiredAt = null): Booking
    {
        return $this->bookingFor($tenant, $departure, $participants, attributes: [
            'hold_expires_at' => $expiredAt ?? CarbonImmutable::now()->subMinute(),
            'created_at' => ($expiredAt ?? CarbonImmutable::now()->subMinute())->subMinutes(120),
        ]);
    }

    /**
     * A valid submit for the departure, as the form sends it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function bookingInput(TripDeparture $departure, array $overrides = []): array
    {
        return [
            'keberangkatan' => $departure->public_id,
            'name' => 'Rina Wulandari',
            'phone' => '0812 3456 7890',
            'participants' => 2,
            'notes' => null,
            'submission_key' => (string) Str::uuid(),
            'price' => 1_250_000,
            ...$overrides,
        ];
    }

    protected function formUrl(string $slug, string $trip, ?string $departure): string
    {
        return '/'.$slug.'/'.$trip.'/pesan'.($departure === null ? '' : '?keberangkatan='.$departure);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function submitBooking(string $slug, string $trip, array $input): TestResponse
    {
        return $this->post('/'.$slug.'/'.$trip.'/pesan', $input);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formProps(string $slug, string $trip, string $departure): array
    {
        return $this->propsOf($this->get($this->formUrl($slug, $trip, $departure)), 'Public/BookingForm');
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingPageProps(string $slug, string $token): array
    {
        return $this->propsOf($this->get('/'.$slug.'/pesanan/'.$token), 'Public/Booking');
    }

    protected function latestBooking(): Booking
    {
        return Booking::acrossOrganizations()->latest('id')->firstOrFail();
    }

    protected function bookingCount(): int
    {
        return Booking::acrossOrganizations()->count();
    }

    /**
     * AC-23: for every departure, seats_taken = Σ participants of its bookings
     * in holding statuses.
     */
    #[After]
    protected function assertSeatInvariant(): void
    {
        if (! $this->app) {
            return;
        }

        $holding = Booking::acrossOrganizations()
            ->whereIn('status', BookingStatus::holdingValues())
            ->get(['trip_departure_id', 'participants'])
            ->groupBy('trip_departure_id')
            ->map(fn ($bookings) => $bookings->sum('participants'));

        foreach (TripDeparture::acrossOrganizations()->get(['id', 'seats_taken', 'capacity']) as $departure) {
            $this->assertSame((int) ($holding[$departure->id] ?? 0), $departure->seats_taken, "Seat invariant broken for departure {$departure->id}.");
            $this->assertLessThanOrEqual($departure->capacity, $departure->seats_taken);
        }
    }
}
