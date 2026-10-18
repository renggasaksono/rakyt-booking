<?php

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for policies of seller-owned models. Cross-tenant access is answered
 * with 404, not 403, so other sellers' ids are never confirmed (F-009 A2).
 * P0 has only the `owner` role; do not assume other roles exist.
 */
abstract class OrganizationOwnedPolicy
{
    protected function owns(User $user, Model $model): Response
    {
        $current = app(CurrentOrganization::class)->id();

        return $current !== null && (int) $model->getAttribute('organization_id') === $current
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Model $model): Response
    {
        return $this->owns($user, $model);
    }

    public function update(User $user, Model $model): Response
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): Response
    {
        return $this->owns($user, $model);
    }
}
