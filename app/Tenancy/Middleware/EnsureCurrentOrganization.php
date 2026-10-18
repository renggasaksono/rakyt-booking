<?php

namespace App\Tenancy\Middleware;

use App\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seller routes need an organization. A signed-in user without one is sent to
 * onboarding step 1 (F-001 E1); JSON and form writes get a 404.
 */
class EnsureCurrentOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->current->check()) {
            abort_unless($request->isMethod('GET') && ! $request->expectsJson(), 404);

            return redirect()->route('onboarding.organization');
        }

        return $next($request);
    }
}
