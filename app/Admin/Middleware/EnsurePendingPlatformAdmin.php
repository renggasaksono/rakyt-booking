<?php

namespace App\Admin\Middleware;

use App\Admin\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin TOTP challenge itself (F-010). The user must already be a
 * platform admin (a seller still gets 403, so the challenge is not a probe for
 * which accounts are admins), but need not have passed the challenge yet. An
 * admin who has already passed it is sent straight on to the admin area.
 */
class EnsurePendingPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->is_platform_admin, 403);
        abort_if($user->two_factor_secret === null, 403);

        if (AdminSession::isConfirmed($request, (int) config('rakyt.admin.session_lifetime'))) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
