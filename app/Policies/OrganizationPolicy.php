<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Auth\Access\Response;

/**
 * Organizations are not organization-scoped models, so route binding finds any
 * of them; this policy is the guard. Only the current organization is visible,
 * others are a 404 (F-009 A2). The platform admin uses admin queries, not this.
 */
class OrganizationPolicy
{
    public function view(User $user, Organization $organization): Response
    {
        return $this->isCurrent($organization);
    }

    public function update(User $user, Organization $organization): Response
    {
        return $this->isCurrent($organization);
    }

    private function isCurrent(Organization $organization): Response
    {
        return app(CurrentOrganization::class)->id() === $organization->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
