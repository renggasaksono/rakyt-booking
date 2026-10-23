<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Onboarding\OnboardingProgress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Daftar" (F-001 A1–A3).
 */
class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'copy' => __('account'),
            'urls' => [
                'store' => route('register'),
                'login' => route('login'),
                'reset' => route('password.request'),
                'terms' => route('legal.terms'),
                'privacy' => route('legal.privacy'),
            ],
            'limits' => ['password_min' => RegisterRequest::PASSWORD_MIN, 'name_max' => RegisterRequest::NAME_MAX],
        ]);
    }

    public function store(RegisterRequest $request, OnboardingProgress $progress): RedirectResponse
    {
        $data = $request->validated();

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'terms_accepted_at' => now(),
                'signup_started_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A3: a double tap raced past validation. If it is the same person,
            // sign them in to the account the first tap created.
            $user = User::query()->where('email', $data['email'])->first();

            if ($user === null || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['email' => __('account.register.email_taken')]);
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->to($progress->landingUrl($user));
        }

        // Sends the queued verification email (non-blocking, A10).
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        // Landing sign-up funnel (F-017 AC-21): the client fires `sign_up` once,
        // after the redirect. Carries no PII — just the milestone name.
        return redirect()->to($progress->landingUrl($user))->with('analytics_event', 'sign_up');
    }
}
