<?php

namespace App\Organizations;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The platform admin suspends or reactivates a seller organization (F-014,
 * ADR-0040). Only the organization row changes — never its trips,
 * departures, bookings or payment records — and the audit event is written
 * in the same transaction (F-015, ADR-0038).
 *
 * The organization row is locked first (the lock order's first step,
 * ADR-0026 §3), so a booking or evidence upload that already holds it
 * finishes before the status changes, and one that comes later sees it.
 * Both actions are idempotent: a no-op changes nothing and writes no event.
 */
final class OrganizationSuspension
{
    public function __construct(private AuditLog $audit) {}

    /**
     * @return bool whether the organization was suspended now
     */
    public function suspend(Organization $organization, User $admin, string $reason): bool
    {
        $reason = self::reason($reason);

        return DB::transaction(function () use ($organization, $admin, $reason) {
            $locked = $this->lock($organization);

            if ($locked->isSuspended()) {
                return false;
            }

            $locked->forceFill([
                'status' => OrganizationStatus::Suspended,
                'suspended_at' => CarbonImmutable::now()->utc(),
                'suspended_by' => $admin->getKey(),
                'suspended_reason' => $reason,
            ])->save();

            $this->audit->record(AuditAction::OrganizationSuspended, $locked,
                before: ['status' => OrganizationStatus::Active->value],
                after: ['status' => OrganizationStatus::Suspended->value],
                actor: Actor::platformAdmin($admin),
                reason: $reason,
            );

            $organization->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Back to `active` with the suspension columns cleared; nothing else changes.
     *
     * @return bool whether the organization was reactivated now
     */
    public function reactivate(Organization $organization, User $admin, string $reason): bool
    {
        $reason = self::reason($reason);

        return DB::transaction(function () use ($organization, $admin, $reason) {
            $locked = $this->lock($organization);

            if (! $locked->isSuspended()) {
                return false;
            }

            $locked->forceFill([
                'status' => OrganizationStatus::Active,
                'suspended_at' => null,
                'suspended_by' => null,
                'suspended_reason' => null,
            ])->save();

            $this->audit->record(AuditAction::OrganizationReactivated, $locked,
                before: ['status' => OrganizationStatus::Suspended->value],
                after: ['status' => OrganizationStatus::Active->value],
                actor: Actor::platformAdmin($admin),
                reason: $reason,
            );

            $organization->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function lock(Organization $organization): Organization
    {
        return Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Both actions require a reason (F-014 AC-1); the request validates it, this is the backstop.
     */
    private static function reason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new LogicException('Suspending or reactivating an organization needs a reason.');
        }

        return $reason;
    }
}
