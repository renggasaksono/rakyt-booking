<?php

namespace App\Tenancy\Middleware;

use App\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seller routes need an organization. A signed-in user without one sees a 404
 * until F-001 (E1) redirects them to onboarding instead.
 */
class EnsureCurrentOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->current->check(), 404);

        return $next($request);
    }
}
