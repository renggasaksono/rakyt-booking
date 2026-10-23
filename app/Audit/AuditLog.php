<?php

namespace App\Audit;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Writes audit events (F-009 A10–A12).
 *
 * Call it inside the same DB transaction as the change, so a rollback
 * removes both:
 *
 *     DB::transaction(function () use ($departure, $audit) {
 *         $departure->update(['capacity' => 20]);
 *         $audit->recordChanges(AuditAction::DepartureCapacityChanged, $departure);
 *     });
 */
class AuditLog
{
    /** Never stored in before/after, whatever the caller passes. */
    private const SECRET_KEY_PATTERN = '/password|token|secret|remember/i';

    public function __construct(private CurrentOrganization $current) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        AuditAction $action,
        Model $subject,
        array $before = [],
        array $after = [],
        ?Actor $actor = null,
    ): AuditEvent {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Write audit events inside the transaction that makes the change.');
        }

        $actor ??= Actor::fromAuth() ?? throw new LogicException('An audit event needs an actor.');

        $event = new AuditEvent([
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'action' => $action->value,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'before' => $this->withoutSecrets($before) ?: null,
            'after' => $this->withoutSecrets($after) ?: null,
            'ip' => $actor->hasIp() ? request()?->ip() : null,
        ]);

        $organizationId = $this->organizationIdFor($subject, $action);

        if ($organizationId === null) {
            // A platform-level action (F-010) belongs to no organization.
            $event->markPlatformLevel();
        } else {
            $event->setAttribute('organization_id', $organizationId);
        }

        $event->save();

        return $event;
    }

    /**
     * Record only the fields the last save() changed, with their previous values.
     */
    public function recordChanges(AuditAction $action, Model $subject, ?Actor $actor = null): AuditEvent
    {
        $after = collect($subject->getChanges())->except([$subject->getUpdatedAtColumn()])->all();
        $before = collect($subject->getPrevious())->only(array_keys($after))->all();

        return $this->record($action, $subject, $before, $after, $actor);
    }

    /**
     * The subject's organization. A user (auth events) is found through their
     * membership, so a guest's password reset can be audited (F-009 R4). It
     * must match the current organization when one is set. Platform-level
     * actions (F-010) belong to no organization and store a null id.
     */
    private function organizationIdFor(Model $subject, AuditAction $action): ?int
    {
        $current = $this->current->id();

        $id = match (true) {
            $subject instanceof Organization => $subject->getKey(),
            $subject instanceof User => ResolveCurrentOrganization::membershipOf($subject)?->organization_id,
            array_key_exists('organization_id', $subject->getAttributes()) => $subject->getAttribute('organization_id'),
            default => $current,
        };

        if ($id === null) {
            // The platform admin has no organization; everything else must.
            if ($action->isPlatformLevel()) {
                return null;
            }

            throw new LogicException('Cannot audit a change without an organization.');
        }

        if ($current !== null && (int) $id !== $current) {
            throw new LogicException('The audited subject belongs to another organization than the current one.');
        }

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $values): array
    {
        return array_filter(
            $values,
            fn (string $key) => ! preg_match(self::SECRET_KEY_PATTERN, $key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
