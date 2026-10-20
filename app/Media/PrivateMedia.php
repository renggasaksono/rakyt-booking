<?php

namespace App\Media;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private files such as payment evidence and QRIS images (F-009 A20, A21; F-006
 * ADR-0030 §3).
 *
 * The private disk is never publicly readable: it is configured `serve => false`
 * (F-006 AC-31 / F-009 R2), so the framework registers no `/storage/{path}`
 * route. A file is reached only after the feature has authorised access (seller
 * policy or customer booking token) and then streamed with `response()`. On an
 * object store that supports it, `temporaryUrl()` issues a short-lived signed URL
 * (TTL ≤ config('rakyt.media.private_url_ttl')); it is never used for the local
 * disk, which has no serving route.
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

    /**
     * Stream a private file inline from an authorised controller. The caller
     * must already have authorised the viewer (AC-19). Never cached (AC-30).
     */
    public static function response(string $path): StreamedResponse
    {
        return Storage::disk(self::disk())->response($path, null, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public static function exists(string $path): bool
    {
        return Storage::disk(self::disk())->exists($path);
    }
}
