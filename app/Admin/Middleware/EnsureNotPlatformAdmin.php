<?php

namespace App\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the platform admin out of every seller route (F-010, ADR-0011):
 * the admin is not a tenant, so onboarding, Akun and the seller app are never
 * theirs — before or after the TOTP challenge. A page visit goes to the admin
 * area, whose gate then asks for TOTP; a write or JSON request gets 403.
 * Runs before route model binding, so a seller URL never 404s or redirects
 * to onboarding first.
 */
class EnsureNotPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_platform_admin) {
            abort_unless($request->isMethod('GET') && ! $request->expectsJson(), 403);

            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
