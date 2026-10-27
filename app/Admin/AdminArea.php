<?php

namespace App\Admin;

use Illuminate\Http\Request;

/**
 * The /admin part of the app (F-010). A platform admin only ever lands inside
 * it: a stale "intended" URL (a guest visit to a seller page before signing in)
 * must never send them to a seller URL.
 */
final class AdminArea
{
    /**
     * Forget the intended URL unless it is an admin page.
     */
    public static function keepIntendedInside(Request $request): void
    {
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && ! self::contains($intended)) {
            $request->session()->forget('url.intended');
        }
    }

    public static function contains(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path === 'admin' || str_starts_with($path, 'admin/');
    }
}
