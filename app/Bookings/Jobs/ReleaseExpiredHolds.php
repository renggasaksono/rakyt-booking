<?php

namespace App\Bookings\Jobs;

use App\Audit\Actor;
use App\Bookings\BookingService;
use App\Models\Booking;
use App\Models\Organization;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

/**
 * Releases unpaid seat holds whose 2 hours are over (F-005 AC-24, ADR-0006,
 * ADR-0026 §7). Runs every minute from the scheduler, synchronously (no
 * queued job, so F-009 R17 does not apply); idempotent.
 *
 * Reviewed cross-organization reader (config/rakyt.php allowlist, F-009 A8):
 * it only lists ids across organizations, then releases each booking as its
 * own organization, under the departure and booking locks, audited as the
 * system (F-009 A11).
 *
 * Correctness never depends on it: holdIsActive() reads the clock, and a new
 * booking releases the expired holds of its departure first (AC-26, AC-27).
 */
final class ReleaseExpiredHolds
{
    public const NAME = 'bookings:release-expired-holds';

    public function __construct(
        private CurrentOrganization $current,
        private BookingService $bookings,
    ) {}

    /**
     * @return int bookings released
     */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $actor = Actor::system(self::NAME);
        $released = 0;

        $due = Booking::acrossOrganizations()
            ->expiredHolds($now)
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id');

        foreach ($due as $organizationId => $bookings) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null) {
                continue;
            }

            $released += $this->current->runAs($organization, fn () => $bookings->filter(
                fn (Booking $booking) => $this->bookings->releaseExpiredHold($booking->getKey(), $now, $actor),
            )->count());
        }

        return $released;
    }
}
