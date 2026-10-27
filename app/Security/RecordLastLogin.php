<?php

namespace App\Security;

use App\Models\User;
use App\Organizations\SuspendedSeller;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;

/**
 * F-012 "last activity": every successful sign-in (password, sign-up,
 * password reset, remember-me) stamps `users.last_login_at`. Written without
 * touching `updated_at`, so it never reads as a profile change. A suspended
 * seller's remember-me cookie authenticates for a moment before the seller is
 * signed out (F-014): that is not a sign-in, so it is not stamped.
 */
final class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || SuspendedSeller::is($event->user)) {
            return;
        }

        $event->user->forceFill(['last_login_at' => CarbonImmutable::now()->utc()]);
        $event->user->timestamps = false;

        try {
            $event->user->saveQuietly();
        } finally {
            $event->user->timestamps = true;
        }
    }
}
