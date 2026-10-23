<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminSession;
use App\Admin\TwoFactorAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin TOTP challenge (F-010, ADR-0011): a platform admin signs in with
 * the usual email + password, then must pass this before reaching any /admin
 * page. Behind `auth` + `platform-admin.pending`; the POST is rate-limited
 * (throttle:admin-2fa), since the 6-digit code space is small.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/TwoFactorChallenge', [
            'copy' => __('admin'),
            'urls' => [
                'verify' => route('admin.two-factor.verify'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function store(Request $request, TwoFactorAuthenticator $totp): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ], attributes: ['code' => __('admin.two_factor.code')]);

        $secret = $request->user()->two_factor_secret;

        if ($secret === null || ! $totp->verify($secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => __('admin.two_factor.invalid')]);
        }

        // A fresh factor is a privilege change: re-key the session (F-001 A-style).
        $request->session()->regenerate();
        AdminSession::confirm($request);

        return redirect()->intended(route('admin.dashboard'));
    }
}
