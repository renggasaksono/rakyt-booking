<?php

namespace App\PublicPages\Middleware;

use App\Models\Organization;
use App\Organizations\BookingSlug;
use App\PublicPages\PublicVisibility;
use App\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages act for the seller in the URL, never for the signed-in user
 * (F-002 AC-7, ADR-0021). Runs after ResolveCurrentOrganization and before
 * route model binding, and replaces whatever the session set.
 *
 * Unknown, reserved and malformed slugs all end in the same 404 (AC-3).
 */
class ResolvePublicOrganization
{
    /** Longest trip slug TripService can produce. */
    private const TRIP_SLUG_MAX = 100;

    public function __construct(
        private CurrentOrganization $current,
        private PublicVisibility $visibility,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Nothing of the signed-in seller's organization is used from here on.
        $this->current->set(null);
        Context::forget('organization_id');

        $slug = $request->route('organization');
        $tripSlug = $request->route('trip');

        abort_unless(self::acceptable($slug) && ($tripSlug === null || self::acceptable($tripSlug)), 404);

        // AC-2: one canonical lower-case URL.
        if (strtolower($slug) !== $slug || ($tripSlug !== null && strtolower($tripSlug) !== $tripSlug)) {
            $path = '/'.strtolower($slug).($tripSlug === null ? '' : '/'.strtolower($tripSlug));
            $query = $request->getQueryString();

            return self::noCache(redirect($path.($query ? '?'.$query : ''), 301));
        }

        abort_unless(BookingSlug::isValidFormat($slug) && ! BookingSlug::isReserved($slug), 404);
        abort_unless($tripSlug === null || (strlen($tripSlug) <= self::TRIP_SLUG_MAX && preg_match('/^[a-z0-9-]+$/', $tripSlug) === 1), 404);

        // Organizations are the tenant itself, not an organization-scoped model.
        $organization = Organization::query()->where('slug', $slug)->first();

        abort_unless($organization !== null && $this->visibility->organizationIsPublic($organization), 404);

        $this->current->set($organization);
        Context::add('organization_id', $organization->getKey());

        return self::noCache($next($request));
    }

    /**
     * Shared Inertia props skip seller data on these routes (AC-7). Read from
     * the matched route, since group middleware runs before this one.
     */
    public static function isPublicPage(Request $request): bool
    {
        return $request->routeIs('public.seller', 'public.trip');
    }

    /**
     * AC-34: seat counts are never served from a cache.
     */
    public static function noCache(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-cache, private');

        return $response;
    }

    /**
     * Short, valid UTF-8, no NUL (ADR-0022 §4): anything else never reaches PostgreSQL.
     */
    private static function acceptable(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && strlen($value) <= 255
            && ! str_contains($value, "\0")
            && mb_check_encoding($value, 'UTF-8');
    }
}
