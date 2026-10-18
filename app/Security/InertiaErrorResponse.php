<?php

namespace App\Security;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-009 R7: an Inertia visit that ends in an error status would show the
 * HTML error page inside Inertia's modal. Send an Inertia page instead, and
 * for an expired form (419) go back to the form with a notice.
 */
final class InertiaErrorResponse
{
    private const PAGES = [403, 404, 409, 429, 500, 503];

    public static function render(Response $response, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! $request->header('X-Inertia') || $response->headers->has('X-Inertia')) {
            return $response;
        }

        if ($status === 419) {
            return back()->with('notice', __('errors.419.message'));
        }

        if (! in_array($status, self::PAGES, true) || ($status === 500 && config('app.debug'))) {
            return $response;
        }

        $message = $status === 429
            ? __('rate_limit.too_many', ['minutes' => max(1, (int) ceil(((int) $response->headers->get('Retry-After', 60)) / 60))])
            : __("errors.{$status}.message");

        return Inertia::render('Error', [
            'status' => $status,
            'title' => __("errors.{$status}.title"),
            'message' => $message,
            'actions' => ['home' => __('errors.home'), 'back' => __('errors.back')],
            'urls' => ['home' => url('/')],
        ])->toResponse($request)->setStatusCode($status);
    }
}
