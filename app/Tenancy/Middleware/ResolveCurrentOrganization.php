<?php

namespace App\Tenancy\Middleware;

use App\Models\OrganizationMember;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Exceptions\AmbiguousOrganizationMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current organization from the signed-in user's membership — never
 * from a route parameter, query string, body or header (F-009 Data & rules).
 * Runs before route model binding so bound models are already scoped.
 */
class ResolveCurrentOrganization
{
    public function __construct(private CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->current->set(null);

        $user = $request->user();

        if ($user !== null) {
            Context::add('user_id', $user->getKey());

            $memberships = OrganizationMember::acrossOrganizations()
                ->with('organization')
                ->where('user_id', $user->getKey())
                ->limit(2)
                ->get();

            if ($memberships->count() > 1) {
                throw new AmbiguousOrganizationMembership;
            }

            if ($membership = $memberships->first()) {
                $this->current->set($membership->organization);
                Context::add('organization_id', $membership->organization_id);
            }
        }

        return $next($request);
    }
}
