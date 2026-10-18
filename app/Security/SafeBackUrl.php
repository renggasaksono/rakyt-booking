<?php

namespace App\Security;

/**
 * The previous URL for "back" links, but only on this site; otherwise the
 * home page. url()->previous() can fall back to the Referer header, which
 * another site controls.
 */
final class SafeBackUrl
{
    public static function get(): string
    {
        $home = url('/');
        $previous = url()->previous($home);

        return $previous === $home || str_starts_with($previous, $home.'/') ? $previous : $home;
    }
}
