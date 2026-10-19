<?php

namespace Tests\Support;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures for F-007. Use with InteractsWithTenants, BuildsTrips,
 * BuildsPublicPages and BuildsBookings (whose seat invariant is checked after
 * every test); the clock is frozen at Thursday 2024-10-10 10:00 WIB.
 */
trait BuildsDashboard
{
    /**
     * Move a seat-holding booking to another holding status — F-006 builds
     * these transitions; seats stay held, so the invariant holds (F-007 intro).
     */
    protected function setStatus(Booking $booking, BookingStatus $status, ?CarbonImmutable $updatedAt = null): Booking
    {
        $this->assertTrue($booking->status->holdsSeats() && $status->holdsSeats(), 'Only between holding statuses.');

        Booking::acrossOrganizations()->whereKey($booking->id)->update([
            'status' => $status->value,
            'updated_at' => ($updatedAt ?? CarbonImmutable::now())->utc(),
        ]);

        return Booking::acrossOrganizations()->findOrFail($booking->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function todayProps(Tenant $tenant): array
    {
        return $this->propsOf($this->actingAsTenant($tenant)->get('/hari-ini'), 'Dashboard/Today');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function listProps(Tenant $tenant, array $query = []): array
    {
        return $this->propsOf($this->actingAsTenant($tenant)->get(route('bookings.index', $query)), 'Bookings/Index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailProps(Tenant $tenant, Booking $booking): array
    {
        return $this->propsOf($this->actingAsTenant($tenant)->get('/pesanan/'.$booking->public_id), 'Bookings/Show');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    protected function references(array $rows): array
    {
        return array_column($rows, 'reference');
    }

    /**
     * Queries run by a callback.
     *
     * @return list<string>
     */
    protected function queriesOf(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }
}
