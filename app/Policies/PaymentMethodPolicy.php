<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\OrganizationOwnedPolicy;
use Illuminate\Auth\Access\Response;

class PaymentMethodPolicy extends OrganizationOwnedPolicy
{
    /**
     * Pengaturan → Rekening (F-001 F5): the seller manages their own
     * organization's accounts; the list itself is scoped to it.
     */
    public function manage(User $user): Response
    {
        return app(CurrentOrganization::class)->check() ? Response::allow() : Response::denyAsNotFound();
    }
}
