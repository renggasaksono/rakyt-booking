<?php

namespace App\Http\Controllers;

use App\Onboarding\OnboardingProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing landing page at `/` (F-017). It is the `home` route,
 * not a `/<slug>` public fallback: it resolves no organization and shows no
 * tenant data (AC-2). Head tags are server-rendered so the link previews and
 * crawls (AC-15, ADR-0024). All copy comes from lang/id/landing.php (ADR-0007).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, OnboardingProgress $progress): Response
    {
        $user = $request->user();

        // AC-3 / Q1: a signed-in seller still sees the landing, but the primary
        // CTA takes them to their dashboard (or onboarding resume step) instead
        // of sign-up. landingUrl() carries no tenant data — only a route.
        $cta = $user === null
            ? ['url' => route('register'), 'label' => __('landing.nav.cta')]
            : ['url' => $progress->landingUrl($user), 'label' => __('landing.nav.cta_authenticated')];

        $copy = __('landing');
        $head = $copy['head'];

        return Inertia::render('Home', [
            'authenticated' => $user !== null,
            'cta' => $cta,
            'copy' => $copy,
            'urls' => [
                'register' => route('register'),
                'login' => route('login'),
                'terms' => route('legal.terms'),
                'privacy' => route('legal.privacy'),
                'home' => route('home'),
            ],
        ])->withViewData('publicHead', [
            'title' => $head['title'],
            'og_title' => $head['og_title'],
            'description' => $head['description'],
            'image' => null,
            'url' => route('home'),
        ]);
    }
}
