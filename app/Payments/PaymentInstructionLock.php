<?php

namespace App\Payments;

use App\Audit\AuditAction;
use App\Models\AuditEvent;
use Carbon\CarbonImmutable;

/**
 * ADR-0018 / F-006 AC-25: payment-instruction changes are blocked for a window
 * after account recovery. The window opens on the stored time of the most recent
 * password reset, sign-in-email change or staff recovery for the current
 * organization — read from the audit log (written in the same transaction as the
 * change), so no auth flow needs a dedicated column. An ordinary password change
 * (AuthPasswordChanged) is deliberately not one of these (AC-26).
 */
final class PaymentInstructionLock
{
    private const TRIGGERS = [
        AuditAction::AuthPasswordReset,
        AuditAction::AuthEmailChanged,
        AuditAction::AuthAccountRecovered,
    ];

    /**
     * When the block lifts, or null when there is none now.
     */
    public function lockedUntil(?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        $hours = self::hours();

        $latest = AuditEvent::query()
            ->whereIn('action', array_map(fn (AuditAction $a) => $a->value, self::TRIGGERS))
            ->where('created_at', '>=', $now->subHours($hours)->utc())
            ->max('created_at');

        if ($latest === null) {
            return null;
        }

        return CarbonImmutable::parse($latest)->addHours($hours);
    }

    public function isLocked(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $until = $this->lockedUntil($now);

        return $until !== null && $until->greaterThan($now);
    }

    public static function hours(): int
    {
        return (int) config('rakyt.media.payment_change_block_hours');
    }
}
