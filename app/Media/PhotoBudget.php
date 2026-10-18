<?php

namespace App\Media;

/**
 * Memory budget for decoding public photos with GD (F-009 R3, ADR-0022 §5).
 *
 * GD holds the whole decoded image in memory: measured on PHP 8.4, about
 * 4.1 bytes per pixel for JPEG and WebP and 7.1 for PNG. The budget uses 8.
 * The headroom covers the booted framework, the ≤ 2560 px copy that is stored
 * and the encoder. The largest accepted photo is derived from these numbers:
 *
 *     max pixels = (memory limit − headroom) / bytes per pixel
 *
 * tests/Feature/Media/PhotoMemoryBudgetTest proves that a photo at the cap is
 * processed under the limit. Change the numbers there, not in code.
 */
final class PhotoBudget
{
    private const MB = 1048576;

    public static function memoryLimitBytes(): int
    {
        return (int) round(self::config('memory_limit_mb') * self::MB);
    }

    public static function headroomBytes(): int
    {
        return (int) round(self::config('memory_headroom_mb') * self::MB);
    }

    public static function bytesPerPixel(): float
    {
        return (float) self::config('bytes_per_pixel');
    }

    public static function maxPixels(): int
    {
        return max(0, (int) floor((self::memoryLimitBytes() - self::headroomBytes()) / self::bytesPerPixel()));
    }

    public static function maxMegapixels(): int
    {
        return intdiv(self::maxPixels(), 1_000_000);
    }

    public static function maxSide(): int
    {
        return (int) self::config('max_side_px');
    }

    /**
     * The stored original's longest side; variants are made from it.
     */
    public static function storedMaxSide(): int
    {
        return (int) self::config('stored_max_side_px');
    }

    /**
     * Raise PHP's memory limit to the budget for this request or job. Never
     * lowers a higher limit and leaves "unlimited" (-1) alone.
     */
    public static function ensureMemoryLimit(): void
    {
        $current = self::toBytes((string) ini_get('memory_limit'));

        if ($current !== -1 && $current < self::memoryLimitBytes()) {
            ini_set('memory_limit', (string) self::memoryLimitBytes());
        }
    }

    private static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * self::MB,
            'm' => $number * self::MB,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function config(string $key): mixed
    {
        return config("rakyt.media.trip_photo.{$key}");
    }
}
