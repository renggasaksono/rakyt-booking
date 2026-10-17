<?php

namespace App\Tenancy;

use App\Models\Organization;

/**
 * The organization the current request or job acts for (ADR-0004).
 *
 * Set only from the signed-in user's membership (ResolveCurrentOrganization),
 * from an explicit id passed to a job (ScopedToOrganization), or by a public
 * page that resolved a seller slug. Never from request input.
 */
final class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    /**
     * Run a callback as the given organization, restoring the previous one afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
