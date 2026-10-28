<?php

namespace App\Admin\Queries;

use App\Bookings\BookingStatus;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Trip;
use App\Organizations\OrganizationStatus;
use App\Trips\TripStatus;

/**
 * Cross-organization counts of the admin overview (F-011, ADR-0011,
 * ADR-0042). Read live from the existing tables — no counter of their own —
 * in one query per table, each served by an index that leads with `status`
 * (no organization is named: the counts span every seller by design).
 */
class AdminOverviewQueries
{
    /**
     * Organizations by account status (AC-4: suspended apart from active).
     *
     * @return array<string, int> every OrganizationStatus value, zero when none
     */
    public function organizationsByStatus(): array
    {
        return $this->byStatus(
            Organization::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
            array_map(fn (OrganizationStatus $status) => $status->value, OrganizationStatus::cases()),
        );
    }

    public function publishedTrips(): int
    {
        return Trip::acrossOrganizations()->where('status', TripStatus::Published->value)->count();
    }

    /**
     * Bookings by the single booking status (AC-2).
     *
     * @return array<string, int> every BookingStatus value, zero when none
     */
    public function bookingsByStatus(): array
    {
        return $this->byStatus(
            Booking::acrossOrganizations()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
            array_map(fn (BookingStatus $status) => $status->value, BookingStatus::cases()),
        );
    }

    /**
     * @param  array<string, int|string>  $counts
     * @param  list<string>  $statuses
     * @return array<string, int>
     */
    private function byStatus(array $counts, array $statuses): array
    {
        return array_combine($statuses, array_map(fn (string $status) => (int) ($counts[$status] ?? 0), $statuses));
    }
}
