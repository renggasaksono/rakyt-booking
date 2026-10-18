<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-blocking email verification (F-001 A10). The link is signed and
 * expires after 60 minutes; it works without being signed in, so a seller can
 * open it on another device.
 */
class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash): SymfonyResponse
    {
        $user = ctype_digit($id) ? User::query()->find((int) $id) : null;

        // The hash ties the link to the address it was sent to (A11: a link for
        // the old email cannot verify the new one).
        $valid = $request->hasValidSignature()
            && $user !== null
            && hash_equals(sha1($user->getEmailForVerification()), $hash);

        if ($valid && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return Inertia::render('Auth/VerifyEmail', [
            'copy' => __('account'),
            'verified' => $valid,
            'urls' => ['continue' => $request->user() ? route('dashboard') : route('login')],
        ])->toResponse($request)->setStatusCode($valid ? 200 : 403);
    }

    /**
     * "Kirim ulang" in the banner; throttled by `verification-resend`.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('toast', __('account.verify.resent'));
    }
}
