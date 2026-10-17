<?php

namespace App\Audit;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Tenancy\CurrentOrganization;
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

        $event->setAttribute('organization_id', $this->organizationIdFor($subject));
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

    private function organizationIdFor(Model $subject): int
    {
        $id = match (true) {
            $subject instanceof Organization => $subject->getKey(),
            array_key_exists('organization_id', $subject->getAttributes()) => $subject->getAttribute('organization_id'),
            default => $this->current->id(),
        };

        return $id ?? throw new LogicException('Cannot audit a change without an organization.');
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
