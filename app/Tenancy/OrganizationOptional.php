<?php

namespace App\Tenancy;

/**
 * A model that uses BelongsToOrganization but may, for specific records,
 * intentionally belong to no organization. The only use is a platform-level
 * audit event (F-010): granting/revoking platform-admin access is not a
 * seller's action, so it has no organization. Seller-owned models never
 * implement this — their organization is always required (F-009 A8a).
 */
interface OrganizationOptional
{
    /**
     * True when this specific record intentionally has no organization.
     */
    public function belongsToNoOrganization(): bool;
}
