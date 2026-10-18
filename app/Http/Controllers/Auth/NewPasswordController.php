<?php

namespace App\Http\Controllers\Auth;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Onboarding\OnboardingProgress;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setting a new password from the emailed link (F-001 A8, A9).
 */
class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        $token = (string) $request->query('token');
        $email = mb_strtolower(trim((string) $request->query('email')));

        return Inertia::render('Auth/ResetPassword', [
            'copy' => __('account'),
            'valid' => $this->tokenIsValid($email, $token),
            'token' => $token,
            'email' => $email,
            'urls' => ['store' => route('password.store'), 'request' => route('password.request')],
            'limits' => ['password_min' => RegisterRequest::PASSWORD_MIN],
        ]);
    }

    public function store(Request $request, AuditLog $audit, OnboardingProgress $progress): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:'.RegisterRequest::PASSWORD_MIN, 'max:255'],
        ], attributes: ['password' => __('account.reset.password')]);

        $data['email'] = mb_strtolower(trim($data['email']));
        $resetUser = null;

        $status = Password::broker()->reset($data, function (User $user, string $password) use ($audit, &$resetUser) {
            DB::transaction(function () use ($user, $password, $audit) {
                $user->password = $password;
                // A9: every remember-me cookie stops working; other sessions are
                // signed out by AuthenticateSession (the password hash changed).
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Users who have not created their organization yet have nothing to audit against.
                if (ResolveCurrentOrganization::membershipOf($user) !== null) {
                    $audit->record(AuditAction::AuthPasswordReset, $user, actor: Actor::user($user));
                }
            });

            event(new PasswordReset($user));
            $resetUser = $user;
        });

        if ($status !== Password::PASSWORD_RESET || $resetUser === null) {
            throw ValidationException::withMessages(['token' => __('account.reset.invalid')]);
        }

        Auth::login($resetUser);
        $request->session()->regenerate();

        return redirect()->to($progress->landingUrl($resetUser))->with('toast', __('account.reset.done'));
    }

    private function tokenIsValid(string $email, string $token): bool
    {
        if ($email === '' || $token === '') {
            return false;
        }

        $user = User::query()->where('email', $email)->first();

        return $user !== null && Password::broker()->tokenExists($user, $token);
    }
}
