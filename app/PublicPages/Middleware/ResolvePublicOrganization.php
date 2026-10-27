<?php

namespace App\PublicPages\Middleware;

use App\Models\Organization;
use App\Organizations\BookingSlug;
use App\PublicPages\PublicVisibility;
use App\PublicPages\UnavailablePage;
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
 * Unknown, reserved and malformed slugs all end in the same 404 (AC-3). A
 * suspended seller's slug is not a 404 but the "tidak tersedia" page (F-014).
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

        // AC-2: one canonical lower-case URL. Only the slug segments are
        // lower-cased (a booking token keeps its case); a form submit is not redirected.
        if (strtolower($slug) !== $slug || ($tripSlug !== null && strtolower($tripSlug) !== $tripSlug)) {
            abort_unless($request->isMethod('GET'), 404);

            $segments = explode('/', $request->path());
            $segments[0] = strtolower($slug);

            if ($tripSlug !== null) {
                $segments[1] = strtolower($tripSlug);
            }

            $query = $request->getQueryString();

            return self::noCache(redirect('/'.implode('/', $segments).($query ? '?'.$query : ''), 301));
        }

        abort_unless(BookingSlug::isValidFormat($slug) && ! BookingSlug::isReserved($slug), 404);
        abort_unless($tripSlug === null || (strlen($tripSlug) <= self::TRIP_SLUG_MAX && preg_match('/^[a-z0-9-]+$/', $tripSlug) === 1), 404);

        // Organizations are the tenant itself, not an organization-scoped model.
        $organization = Organization::query()->where('slug', $slug)->first();

        abort_unless($organization !== null, 404);

        // F-014 AC-3/AC-4: a suspended seller's link is the neutral "tidak tersedia"
        // page (200, not 404); a booking submit goes there too. Only a customer's
        // own booking (token routes) stays reachable, where uploads are refused.
        if (! $this->visibility->organizationIsPublic($organization) && ! self::isBookingRecord($request)) {
            return self::noCache(
                $request->isMethodSafe()
                    ? UnavailablePage::response($request)
                    : redirect('/'.$organization->slug, 303),
                self::isBookingPage($request),
            );
        }

        $this->current->set($organization);
        Context::add('organization_id', $organization->getKey());

        return self::noCache($next($request), self::isBookingPage($request));
    }

    /**
     * Shared Inertia props skip seller data on these routes (AC-7). Read from
     * the matched route, since group middleware runs before this one.
     */
    public static function isPublicPage(Request $request): bool
    {
        return $request->routeIs('public.seller', 'public.trip') || self::isBookingPage($request);
    }

    /**
     * The customer booking routes (F-005, ADR-0027).
     */
    public static function isBookingPage(Request $request): bool
    {
        return $request->routeIs('public.booking.*');
    }

    /**
     * The customer's own booking behind its token (F-005 AC-29, F-006): the
     * page, its evidence and the upload. These stay reachable while the seller
     * is suspended — the booking record stays valid (F-014). The seller's QRIS
     * image does not: no payment instructions while suspended (F-014 Q1).
     */
    public static function isBookingRecord(Request $request): bool
    {
        return $request->routeIs('public.booking.show', 'public.booking.evidence', 'public.booking.evidence.show');
    }

    /**
     * AC-34: seat counts are never served from a cache. Booking pages carry
     * customer data and are never stored at all (F-005 AC-36).
     */
    public static function noCache(Response $response, bool $noStore = false): Response
    {
        $response->headers->set('Cache-Control', $noStore ? 'no-store, private' : 'no-cache, private');

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
