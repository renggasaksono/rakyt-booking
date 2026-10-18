<?php

namespace App\Http\Middleware;

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
        return [
            ...parent::share($request),
            'flash' => fn () => [
                'toast' => $request->session()->get('toast'),
                'notice' => $request->session()->get('notice'),
            ],
            // Seller layout copy and links (F-003 minimal layout, F-001 Pengaturan); F-007 adds more items.
            'seller' => fn () => $request->user() === null || ! app(CurrentOrganization::class)->check() ? null : [
                'nav' => [
                    ['key' => 'trips', 'label' => __('trips.nav.trips'), 'url' => route('trips.index'), 'active' => $request->routeIs('trips.*', 'departures.*')],
                    ['key' => 'settings', 'label' => __('profile.settings.title'), 'url' => route('settings.index'), 'active' => $request->routeIs('settings.*')],
                ],
                'brand' => __('trips.nav.brand'),
                'menu_label' => __('trips.nav.menu'),
                'close_label' => __('trips.nav.close'),
            ],
            // F-001 A6, A10: account menu and the non-blocking verification banner.
            'account' => fn () => $request->user() === null ? null : [
                'name' => $request->user()->name,
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
}
