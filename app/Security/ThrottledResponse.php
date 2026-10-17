<?php

namespace App\Security;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a hit rate limit (F-009 A14).
 *
 * Inertia form submits go back to the form with the message as a validation
 * error and the typed input kept; JSON gets 429 + message; anything else the
 * Bahasa 429 page.
 */
final class ThrottledResponse
{
    private const NEVER_FLASH = ['password', 'password_confirmation', 'current_password', '_token'];

    public static function render(ThrottleRequestsException $e, Request $request): ?Response
    {
        $message = self::message($e);

        if ($request->header('X-Inertia') && ! $request->isMethod('GET')) {
            return back()
                ->withInput($request->except(self::NEVER_FLASH))
                ->withErrors(['rate_limit' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429, $e->getHeaders());
        }

        return null;
    }

    public static function message(ThrottleRequestsException $e): string
    {
        $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

        return __('rate_limit.too_many', ['minutes' => max(1, (int) ceil($seconds / 60))]);
    }
}
