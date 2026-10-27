<?php

namespace App\PublicPages;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The neutral "tidak tersedia" state of a seller link whose organization is
 * not public (F-014 AC-4, PublicVisibility::organizationIsPublic()): HTTP 200,
 * not a 404, and nothing of the seller — no name, profile, trips or booking
 * CTA. Its props are fixed copy only; the organization is never passed in.
 */
final class UnavailablePage
{
    public static function response(Request $request): Response
    {
        $copy = __('public.unavailable');

        return Inertia::render('Public/Unavailable', [
            'title' => $copy['title'],
            'copy' => [...$copy, 'footer' => __('public.footer')],
            'urls' => ['home' => route('home')],
        ])
            ->withViewData('plainHead', ['title' => $copy['title'], 'noindex' => true])
            ->toResponse($request);
    }
}
