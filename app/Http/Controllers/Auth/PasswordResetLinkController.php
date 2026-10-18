<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Lupa password?" (F-001 A7, F-009 A15a). The answer never says whether the
 * email has an account: same message, same redirect, comparable timing.
 */
class PasswordResetLinkController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'copy' => __('account'),
            'status' => $request->session()->get('status'),
            'urls' => ['store' => route('password.email'), 'login' => route('login')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']], attributes: ['email' => __('account.forgot.email')]);
        $email = mb_strtolower(trim($data['email']));

        // The broker runs inside a timebox, so a known and an unknown email
        // take the same time. Its status (sent, unknown user, throttled) is
        // never shown: reporting it would reveal the account.
        Password::broker()->sendResetLink(['email' => $email]);

        return back()->with('status', __('account.forgot.sent'));
    }
}
