<?php

namespace App\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Private files such as payment evidence (F-009 A20, A21).
 *
 * The private disk is never publicly readable. Call temporaryUrl() only after
 * the feature has authorised access (seller policy or customer booking
 * token); the URL expires within config('rakyt.media.private_url_ttl') minutes.
 */
final class PrivateMedia
{
    public static function disk(): string
    {
        return config('rakyt.media.private_disk');
    }

    public static function temporaryUrl(string $path): string
    {
        $ttl = min(5, (int) config('rakyt.media.private_url_ttl'));

        return Storage::disk(self::disk())->temporaryUrl($path, now()->addMinutes($ttl));
    }
}
