<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\Contracts\PaymentReadiness;
use Carbon\CarbonImmutable;

/**
 * Trip fixtures for F-003 tests. Use with InteractsWithTenants.
 *
 * The clock is frozen at Thursday 2024-10-10 10:00 WIB so dates match the
 * mockups (a departure on "Kam, 24 Okt" closes "Senin, 21 Okt" with H-3).
 */
trait BuildsTrips
{
    protected function freezeClock(string $local = '2024-10-10 10:00', string $timezone = 'Asia/Jakarta'): CarbonImmutable
    {
        $now = CarbonImmutable::parse($local, $timezone);
        $this->travelTo($now);

        return $now;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function tripFor(Tenant $tenant, array $attributes = [], bool $published = false): Trip
    {
        return $this->asTenant($tenant, function () use ($attributes, $published) {
            $factory = Trip::factory();

            return ($published ? $factory->published() : $factory)->create($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function departureFor(Tenant $tenant, Trip $trip, string $startDate = '2024-10-24', array $attributes = [], int $seatsTaken = 0): TripDeparture
    {
        return $this->asTenant($tenant, fn () => TripDeparture::factory()
            ->startingOn($startDate, $tenant->organization->timezone)
            ->withSeatsTaken($seatsTaken)
            ->create(['trip_id' => $trip->id, ...$attributes]));
    }

    protected function withPaymentMethod(bool $ready = true): void
    {
        $this->app->instance(PaymentReadiness::class, new class($ready) implements PaymentReadiness
        {
            public function __construct(private bool $ready) {}

            public function hasActivePaymentMethod(Organization $organization): bool
            {
                return $this->ready;
            }
        });
    }

    protected function freshTrip(Trip $trip): Trip
    {
        return Trip::acrossOrganizations()->findOrFail($trip->id);
    }

    protected function freshDeparture(TripDeparture $departure): TripDeparture
    {
        return TripDeparture::acrossOrganizations()->findOrFail($departure->id);
    }
}
