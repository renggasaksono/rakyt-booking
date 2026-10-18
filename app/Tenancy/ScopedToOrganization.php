<?php

namespace App\Tenancy;

use App\Models\Organization;
use Closure;

/**
 * Job middleware: runs a queued job as an explicit organization (F-009 A8 option a).
 *
 *     public function middleware(): array
 *     {
 *         return [new ScopedToOrganization($this->organizationId)];
 *     }
 */
class ScopedToOrganization
{
    public function __construct(private int $organizationId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $organization = Organization::query()->findOrFail($this->organizationId);

        return app(CurrentOrganization::class)->runAs($organization, fn () => $next($job));
    }
}
