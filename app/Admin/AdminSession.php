<?php

namespace App\Admin;

use Illuminate\Http\Request;

/**
 * The admin's two-factor session state (F-010). The admin's access window is a
 * rolling idle timeout shorter than the seller session (config rakyt.admin.
 * session_lifetime): once idle past it, the TOTP challenge is required again.
 */
final class AdminSession
{
    private const CONFIRMED_AT = 'admin.2fa.confirmed_at';

    private const LAST_SEEN = 'admin.2fa.last_seen';

    /**
     * Record a passed TOTP challenge for this session.
     */
    public static function confirm(Request $request): void
    {
        $now = now()->getTimestamp();
        $request->session()->put(self::CONFIRMED_AT, $now);
        $request->session()->put(self::LAST_SEEN, $now);
    }

    /**
     * True when the admin passed the challenge and has not been idle longer than
     * the admin session lifetime.
     */
    public static function isConfirmed(Request $request, int $lifetimeMinutes): bool
    {
        $confirmedAt = $request->session()->get(self::CONFIRMED_AT);
        $lastSeen = $request->session()->get(self::LAST_SEEN);

        if ($confirmedAt === null || $lastSeen === null) {
            return false;
        }

        return (now()->getTimestamp() - (int) $lastSeen) <= $lifetimeMinutes * 60;
    }

    /**
     * Mark fresh activity, so an active admin's window keeps rolling forward.
     */
    public static function touch(Request $request): void
    {
        $request->session()->put(self::LAST_SEEN, now()->getTimestamp());
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([self::CONFIRMED_AT, self::LAST_SEEN]);
    }
}
