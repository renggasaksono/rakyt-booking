<?php

namespace App\PublicPages;

use App\Models\Organization;
use App\Models\Trip;
use App\Trips\TripStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that decides what the public pages may show (F-002 AC-1,
 * AC-5, Q11), including F-014's suspension rule in organizationIsPublic().
 */
final class PublicVisibility
{
    /**
     * Every organization is public from onboarding step 1 (F-002 Q3) unless
     * the platform admin suspended it (F-014): then its link shows the neutral
     * "tidak tersedia" page (UnavailablePage) — no trips, no booking, no profile.
     */
    public function organizationIsPublic(Organization $organization): bool
    {
        return ! $organization->isSuspended();
    }

    /**
     * Published trips of the current organization. Drafts never leave the
     * server (AC-5); the organization scope keeps other sellers out (AC-6).
     *
     * @return Builder<Trip>
     */
    public function trips(): Builder
    {
        return Trip::query()->where('status', TripStatus::Published);
    }
}
