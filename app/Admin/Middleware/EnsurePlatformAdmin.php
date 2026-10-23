<?php

namespace App\Admin\Middleware;

use App\Admin\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The server-side gate on every /admin page (F-010, ADR-0011). Security does
 * not depend on hidden links: a seller, whatever URL they type, is forbidden.
 *
 * - guest → handled upstream by `auth` (redirected to sign-in);
 * - signed-in non-admin → 403;
 * - admin who has not passed (or whose window has expired) TOTP → the challenge.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The flag is the only thing that opens the gate (F-010 AC).
        abort_unless($user !== null && $user->is_platform_admin, 403);

        // An admin is always enrolled in TOTP at grant time; a missing secret is
        // a misconfiguration, never an open door.
        abort_if($user->two_factor_secret === null, 403);

        $lifetime = (int) config('rakyt.admin.session_lifetime');

        if (! AdminSession::isConfirmed($request, $lifetime)) {
            AdminSession::forget($request);

            return redirect()->guest(route('admin.two-factor.challenge'));
        }

        AdminSession::touch($request);

        return $next($request);
    }
}
