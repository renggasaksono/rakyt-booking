<?php

namespace App\Organizations;

use App\Models\User;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A seller whose organization the platform admin suspended cannot use the
 * seller app at all (F-014, no read-only mode in P0): sign-in is refused
 * with a neutral "akun ditangguhkan" message, and a session that was
 * already open is signed out on its next request (ADR-0040).
 */
final class SuspendedSeller
{
    /** Session flash key the sign-in page shows. */
    public const FLASH = 'suspended';

    public static function is(User $user): bool
    {
        return ResolveCurrentOrganization::membershipOf($user)?->organization?->isSuspended() === true;
    }

    /**
     * Sign the user out everywhere this session reaches — the remember-me
     * token is cycled by logout() — and send them to the sign-in page with
     * the neutral notice. JSON callers get a 403 instead of a redirect.
     */
    public static function signOut(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('account.suspended')], 403);
        }

        return self::toSignIn();
    }

    public static function toSignIn(): RedirectResponse
    {
        return redirect()->route('login', status: 303)->with(self::FLASH, __('account.suspended'));
    }
}
