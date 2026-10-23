<?php

namespace App\Http\Controllers\Settings;

use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Notifications\Auth\EmailChangedNotification;
use App\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Akun (F-001 A6, A11, A12, F3). Acts only on the signed-in
 * user; any user id in the request is ignored (T6).
 */
class AccountController extends Controller
{
    public function edit(Request $request, CurrentOrganization $current): Response
    {
        $user = $request->user();
        $organization = $current->get();

        return Inertia::render('Settings/Account', [
            'copy' => __('account'),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->hasVerifiedEmail(),
            ],
            'urls' => [
                'email' => route('settings.account.email'),
                'password' => route('settings.account.password'),
                'back' => $current->check() ? route('settings.index') : route('onboarding'),
            ],
            'backLabel' => $current->check() ? __('profile.settings.back') : __('account.settings.back'),
            'limits' => ['password_min' => RegisterRequest::PASSWORD_MIN],
            // Past onboarding the page sits in the seller layout; during it, a bare frame (no seller nav yet).
            'inApp' => $organization !== null && ! $organization->isOnboarding(),
        ]);
    }

    /**
     * A11: the email changes now; it needs verifying again, the old address
     * is told and other devices are signed out (ADR-0018).
     */
    public function updateEmail(Request $request, AuditLog $audit, CurrentOrganization $current): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', RegisterRequest::unusedEmail($user)],
            'current_password' => ['required', 'string'],
        ], attributes: ['email' => __('account.settings.new_email'), 'current_password' => __('account.settings.current_password')]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('account.settings.wrong_password')]);
        }

        if ($data['email'] === $user->email) {
            throw ValidationException::withMessages(['email' => __('account.settings.same_email')]);
        }

        $old = $user->email;

        DB::transaction(function () use ($user, $data, $old, $audit, $current) {
            $user->email = $data['email'];
            $user->email_verified_at = null;
            $user->setRememberToken(Str::random(60));
            $user->save();

            if ($current->check()) {
                $audit->record(AuditAction::AuthEmailChanged, $user, ['email' => $old], ['email' => $user->email]);
            }
        });

        // ADR-0018: an email change signs out every other device; this one stays.
        Auth::logoutOtherDevices($data['current_password']);

        $user->sendEmailVerificationNotification();
        Notification::route('mail', $old)->notify(new EmailChangedNotification(EmailChangedNotification::mask($user->email)));

        return back()->with('toast', __('account.settings.email_changed'));
    }

    /**
     * A12: other sessions end (AuthenticateSession sees the new hash); this one stays.
     */
    public function updatePassword(Request $request, AuditLog $audit, CurrentOrganization $current): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:'.RegisterRequest::PASSWORD_MIN, 'max:255'],
        ], attributes: ['current_password' => __('account.settings.current_password'), 'password' => __('account.settings.new_password')]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('account.settings.wrong_password')]);
        }

        DB::transaction(function () use ($user, $data, $audit, $current) {
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
            $user->save();

            if ($current->check()) {
                $audit->record(AuditAction::AuthPasswordChanged, $user);
            }
        });

        // Other sessions and remember-me cookies no longer match the password
        // hash (AuthenticateSession); this device is kept signed in.
        Auth::logoutOtherDevices($data['password']);

        return back()->with('toast', __('account.settings.password_changed'));
    }
}
