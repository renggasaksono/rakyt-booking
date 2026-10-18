<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Models\User;

/**
 * A seller organization and its owner, for cross-tenant tests.
 */
final readonly class Tenant
{
    public function __construct(
        public Organization $organization,
        public User $owner,
    ) {}

    public function id(): int
    {
        return $this->organization->getKey();
    }
}
