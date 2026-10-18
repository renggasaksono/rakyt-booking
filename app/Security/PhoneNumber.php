<?php

namespace App\Security;

/**
 * Indonesian mobile numbers in E.164 (F-009 A15): `0812…`, `+62812…` and
 * `62812…` all become `+62812…`.
 */
final class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if ($digits === '' || $digits === null) {
            return null;
        }

        // International dialling prefix: 0062… is +62…
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $national = match (true) {
            str_starts_with($digits, '62') => substr($digits, 2),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        return '+62'.ltrim($national, '0');
    }

    /**
     * `+6281234567890` → `+62812****890`, for logs (A24).
     */
    public static function mask(string $e164): string
    {
        $length = strlen($e164);

        if ($length <= 9) {
            return str_repeat('*', $length);
        }

        return substr($e164, 0, 6).'****'.substr($e164, -3);
    }
}
