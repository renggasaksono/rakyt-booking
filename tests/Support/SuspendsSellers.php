<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Organizations\OrganizationStatus;
use Carbon\CarbonImmutable;

/**
 * F-014 fixtures: put an organization in the suspended state directly, as
 * App\Organizations\OrganizationSuspension leaves it (status + when + why;
 * the organizations_suspension_check constraint needs all of them). Tests of
 * the admin action itself go through the service or the route instead.
 */
trait SuspendsSellers
{
    protected function suspendOrganization(Organization|Tenant $organization, string $reason = 'Laporan penipuan dari pelanggan'): Organization
    {
        $organization = $organization instanceof Tenant ? $organization->organization : $organization;

        $organization->forceFill([
            'status' => OrganizationStatus::Suspended,
            'suspended_at' => CarbonImmutable::now()->utc(),
            'suspended_reason' => $reason,
        ])->save();

        return $organization;
    }

    protected function reactivateOrganization(Organization|Tenant $organization): Organization
    {
        $organization = $organization instanceof Tenant ? $organization->organization : $organization;

        $organization->forceFill([
            'status' => OrganizationStatus::Active,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspended_reason' => null,
        ])->save();

        return $organization;
    }
}
