<?php

namespace App\Tenancy\Middleware;

use App\Models\OrganizationMember;
use App\Models\User;
use App\Organizations\SuspendedSeller;
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
 *
 * A suspended organization is never resolved: its member is signed out on
 * this request, whatever the route (F-014 AC-5). Public pages drop this
 * middleware, so a signed-in visitor is never affected by the seller in the URL.
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

            if ($membership = self::membershipOf($user)) {
                if ($membership->organization->isSuspended()) {
                    return SuspendedSeller::signOut($request);
                }

                $this->current->set($membership->organization);
                Context::add('organization_id', $membership->organization_id);
            }
        }

        return $next($request);
    }

    /**
     * The user's one membership (P0: one organization per user), read without
     * the organization scope because it is what decides the organization.
     * Also used after a write that may have created it (F-001 B9) and to find
     * a guest's organization for an audit event (F-009 R4).
     */
    public static function membershipOf(User $user): ?OrganizationMember
    {
        $memberships = OrganizationMember::acrossOrganizations()
            ->with('organization')
            ->where('user_id', $user->getKey())
            ->limit(2)
            ->get();

        if ($memberships->count() > 1) {
            throw new AmbiguousOrganizationMembership;
        }

        return $memberships->first();
    }
}
