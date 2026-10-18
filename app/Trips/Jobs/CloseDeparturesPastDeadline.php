<?php

namespace App\Trips\Jobs;

use App\Audit\Actor;
use App\Models\Organization;
use App\Models\TripDeparture;
use App\Tenancy\CurrentOrganization;
use App\Trips\DepartureService;
use App\Trips\DepartureStatus;
use Carbon\CarbonImmutable;

/**
 * Closes open departures whose booking deadline has passed (F-003 AC-37).
 * Runs every minute from the scheduler; idempotent.
 *
 * Reviewed cross-organization reader (config/rakyt.php allowlist, F-009 A8):
 * it only lists ids across organizations, then changes each departure as its
 * own organization, under the row lock, audited as the system (F-009 A11).
 *
 * Correctness never depends on it: bookability checks the deadline at request
 * time (AC-36). Closing only makes the seller's list say "Tutup".
 */
final class CloseDeparturesPastDeadline
{
    public const NAME = 'departures:close-past-deadline';

    public function __construct(
        private CurrentOrganization $current,
        private DepartureService $departures,
    ) {}

    /**
     * @return int departures closed
     */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        // Bindings are written without an offset, so compare in UTC (ADR-0014).
        $now = ($now ?? CarbonImmutable::now())->utc();
        $actor = Actor::system(self::NAME);
        $closed = 0;

        $due = TripDeparture::acrossOrganizations()
            ->where('status', DepartureStatus::Open)
            ->where('booking_deadline_at', '<=', $now)
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id');

        foreach ($due as $organizationId => $departures) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null) {
                continue;
            }

            $closed += $this->current->runAs($organization, fn () => $departures->filter(
                fn (TripDeparture $departure) => $this->departures->closeAtDeadline($departure->getKey(), $now, $actor),
            )->count());
        }

        return $closed;
    }
}
