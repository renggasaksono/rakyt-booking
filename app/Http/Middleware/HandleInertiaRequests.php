<?php

namespace App\Http\Middleware;

use App\Bookings\BookingFilters;
use App\Dashboard\DashboardPresenter;
use App\PublicPages\Middleware\ResolvePublicOrganization;
use App\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Customer booking pages show nothing of the signed-in seller and no
        // analytics — the sign-up funnel is for sellers (F-002 AC-7, AC-8; F-017).
        if (ResolvePublicOrganization::isPublicPage($request)) {
            return parent::share($request);
        }

        // Landing sign-up-funnel analytics (F-017) loads only on the funnel pages
        // (landing → register/login → onboarding), never across the seller app.
        $shared = $request->routeIs('home', 'register', 'login', 'onboarding', 'onboarding.*')
            ? [...parent::share($request), 'analytics' => fn () => $this->analytics($request)]
            : parent::share($request);

        // The marketing landing (F-017 AC-2): the funnel analytics prop, but
        // none of a signed-in seller's navigation, name or action counts.
        if ($request->routeIs('home')) {
            return $shared;
        }

        return [
            ...$shared,
            'flash' => fn () => [
                'toast' => $request->session()->get('toast'),
                'notice' => $request->session()->get('notice'),
            ],
            // Seller navigation (F-007 AC-2–AC-4): desktop side nav, phone bottom nav, Perlu tindakan counts.
            'seller' => fn () => $request->user() === null || ! app(CurrentOrganization::class)->check() ? null : $this->sellerNav($request),
            // F-010: admin shell navigation. Only ever present for a platform admin;
            // F-011–F-015 add their items here.
            'admin' => fn () => $request->user()?->is_platform_admin ? $this->adminNav($request) : null,
            // F-001 A6, A10: account menu and the non-blocking verification banner.
            'account' => fn () => $request->user() === null ? null : [
                'verified' => $request->user()->hasVerifiedEmail(),
                'copy' => [
                    'banner' => __('account.verify.banner'),
                    'resend' => __('account.verify.resend'),
                    'dismiss' => __('account.verify.dismiss'),
                    'logout' => __('account.menu.logout'),
                    'account' => __('account.menu.account'),
                ],
                'urls' => [
                    'resend' => route('verification.send'),
                    'logout' => route('logout'),
                    'account' => route('settings.account'),
                ],
            ],
        ];
    }

    /**
     * Landing sign-up-funnel analytics (F-017, ADR-0034). The client loads GA
     * only when a measurement id is configured (unset in tests and local by
     * default, so nothing is emitted — AC-19). `event` carries a server-flashed
     * funnel milestone (sign_up, onboarding_start, onboarding_publish) so the
     * client can fire it after the Inertia redirect; it never contains PII.
     *
     * @return array{measurementId: ?string, event: ?string}
     */
    private function analytics(Request $request): array
    {
        return [
            // Null (tests and local default) disables GA entirely — no script,
            // no network call, no cookie (AC-19).
            'measurementId' => config('services.google_analytics.measurement_id'),
            // A server-flashed funnel milestone the client fires after the
            // redirect (sign_up, onboarding_start, onboarding_publish). No PII.
            'event' => $request->hasSession() ? $request->session()->get('analytics_event') : null,
        ];
    }

    /**
     * Desktop: Hari ini, Pesanan, Trip & keberangkatan, Pembayaran, Halaman
     * publik, Pengaturan; the phone shows the items with `phone` (Q2). Pesanan
     * and Pembayaran carry the Perlu tindakan count — one query, current
     * organization only, seller pages only (public pages return early above).
     *
     * @return array<string, mixed>
     */
    private function sellerNav(Request $request): array
    {
        $organization = app(CurrentOrganization::class)->get();
        $count = DashboardPresenter::actionNeededCount();
        $badge = $count > 0 ? ['count' => $count, 'label' => __('dashboard.nav.badge', ['n' => $count])] : null;
        $payments = $request->routeIs('bookings.index') && $request->query('status') === BookingFilters::ACTION_NEEDED;

        $item = fn (string $key, string $label, string $url, bool $active, array $extra = []) => [
            'key' => $key,
            'label' => $label,
            'phone_label' => null,
            'url' => $url,
            'active' => $active,
            'badge' => null,
            'external' => false,
            'phone' => false,
            ...$extra,
        ];

        return [
            'nav' => [
                $item('today', __('dashboard.nav.today'), route('dashboard'), $request->routeIs('dashboard'), ['phone' => true]),
                $item('bookings', __('dashboard.nav.bookings'), route('bookings.index'), $request->routeIs('bookings.*') && ! $payments, ['phone' => true, 'badge' => $badge]),
                $item('trips', __('dashboard.nav.trips'), route('trips.index'), $request->routeIs('trips.*', 'departures.*'), ['phone' => true, 'phone_label' => __('dashboard.nav.trips_short')]),
                $item('payments', __('dashboard.nav.payments'), route('bookings.index', ['status' => BookingFilters::ACTION_NEEDED]), $payments, ['badge' => $badge]),
                $item('public_page', __('dashboard.nav.public_page'), route('public.seller', ['organization' => $organization->slug]), false, ['external' => true]),
                $item('settings', __('dashboard.nav.settings'), route('settings.index'), $request->routeIs('settings.*'), ['phone' => true]),
            ],
            'brand' => __('trips.nav.brand'),
            'brand_suffix' => __('trips.nav.brand_suffix'),
            // AC-5: business name and plan sit at the foot of the desktop side nav (1e/2f), on every seller page.
            'organization' => [
                'name' => $organization->name,
                'plan_label' => __('dashboard.plan.free'),
            ],
            'menu_label' => __('trips.nav.menu'),
            'close_label' => __('trips.nav.close'),
            'external_mark' => __('dashboard.nav.external'),
            'new_tab' => __('dashboard.nav.new_tab'),
        ];
    }

    /**
     * Admin shell navigation (F-010). A separate boundary from the seller nav:
     * the admin is not a tenant and sees none of a seller's menu. F-011–F-015
     * add their sections to this list.
     *
     * @return array<string, mixed>
     */
    private function adminNav(Request $request): array
    {
        return [
            'brand' => __('admin.brand'),
            'menu_label' => __('admin.nav.menu'),
            'signed_in_as' => __('admin.nav.signed_in_as', ['name' => $request->user()->name]),
            'nav' => [
                [
                    'key' => 'overview',
                    'label' => __('admin.nav.overview'),
                    'url' => route('admin.dashboard'),
                    'active' => $request->routeIs('admin.dashboard'),
                ],
            ],
            'logout' => [
                'label' => __('admin.nav.logout'),
                'url' => route('logout'),
            ],
        ];
    }
}
