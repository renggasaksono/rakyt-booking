<?php

namespace App\PublicPages;

use App\Models\Organization;
use App\Models\Trip;
use App\Trips\TripStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that decides what the public pages may show (F-002 AC-1,
 * AC-5, Q11). F-014 adds its suspension rule to organizationIsPublic().
 */
final class PublicVisibility
{
    /**
     * Every organization is public from onboarding step 1 (F-002 Q3), until
     * F-014 decides how suspension works.
     */
    public function organizationIsPublic(Organization $organization): bool
    {
        return true;
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
