<?php

namespace App\Security;

/**
 * Indonesian mobile numbers in E.164 (F-009 A15): `0812…`, `+62812…` and
 * `62812…` all become `+62812…`.
 */
final class PhoneNumber
{
    /** +62 8 followed by 8–11 digits (F-001 B3, F-005 AC-10). */
    public const MOBILE_PATTERN = '/^\+628\d{8,11}$/';

    /**
     * An E.164 Indonesian mobile number, as normalize() returns it.
     */
    public static function isMobile(?string $e164): bool
    {
        return $e164 !== null && preg_match(self::MOBILE_PATTERN, $e164) === 1;
    }

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
