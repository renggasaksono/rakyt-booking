<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Onboarding\OnboardingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Masuk" and "Keluar" (F-001 A4–A6). Throttled per email + IP by the
 * `seller-login` limiter (F-009).
 */
class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'copy' => __('account'),
            'status' => $request->session()->get('status'),
            'urls' => [
                'store' => route('login'),
                'forgot' => route('password.request'),
                'register' => route('register'),
            ],
        ]);
    }

    public function store(Request $request, OnboardingProgress $progress): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], attributes: ['email' => __('account.login.email'), 'password' => __('account.login.password')]);

        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // A4: the same message for an unknown email and a wrong password.
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended($progress->landingUrl($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // A6: logout() also cycles the remember-me token, so an old cookie no longer signs in.
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
