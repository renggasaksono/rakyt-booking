<?php

namespace App\Audit;

/**
 * What an audit event may never hold (F-009 A10, F-015 AC-3): passwords, OTP
 * and TOTP codes, 2FA secrets, tokens, payment evidence, and customer details
 * beyond IDs. Applied by AuditLog before a write and again by the admin view
 * before display, at any depth.
 */
final class AuditRedaction
{
    private const SENSITIVE_KEY_PATTERN = '/password|token|secret|remember|otp|two_factor|recovery|^code$|evidence|^customer_(?!id$)|^notes$/i';

    public const MAX_REASON_LENGTH = 500;

    public static function isSensitive(string $key): bool
    {
        return (bool) preg_match(self::SENSITIVE_KEY_PATTERN, $key);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function strip(array $values): array
    {
        $kept = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                continue;
            }

            $kept[$key] = is_array($value) ? self::strip($value) : $value;
        }

        return $kept;
    }

    /**
     * A trimmed reason, or null when blank.
     */
    public static function reason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, self::MAX_REASON_LENGTH);
    }
}
