<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\OrganizationOwnedPolicy;
use Illuminate\Auth\Access\Response;

/**
 * Any member of the trip's organization may manage it in the MVP (F-003 Data & rules).
 */
class TripPolicy extends OrganizationOwnedPolicy
{
    /**
     * Listing is scoped to the current organization; without one there is nothing to list.
     */
    public function viewAny(User $user): Response
    {
        return $this->hasOrganization();
    }

    /**
     * New trips belong to the current organization.
     */
    public function create(User $user): Response
    {
        return $this->hasOrganization();
    }

    private function hasOrganization(): Response
    {
        return app(CurrentOrganization::class)->check() ? Response::allow() : Response::denyAsNotFound();
    }
}
