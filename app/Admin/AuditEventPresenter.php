<?php

namespace App\Admin;

use App\Admin\Queries\AuditEventQueries;
use App\Audit\AuditRedaction;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Audit events as admin view rows (F-015). Names of organizations and users
 * are resolved in one query each; before/after are redacted again before
 * display (AC-3), so even a row written around AuditLog shows no secret.
 * Customers appear by booking id only.
 */
class AuditEventPresenter
{
    /** The platform admin works in WIB (ADR-0014's default zone). */
    public const TIMEZONE = 'Asia/Jakarta';

    private const USER_ACTORS = ['user', 'platform_admin'];

    public function __construct(private AuditEventQueries $queries) {}

    /**
     * @param  iterable<AuditEvent>  $events
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $events): array
    {
        $events = collect($events);

        $organizations = $this->queries->organizationsById($this->ids(
            $events->pluck('organization_id')
                ->merge($events->where('subject_type', (new Organization)->getMorphClass())->pluck('subject_id')),
        ));

        $users = $this->queries->usersById($this->ids(
            $events->whereIn('actor_type', self::USER_ACTORS)->pluck('actor_id')
                ->merge($events->where('subject_type', (new User)->getMorphClass())->pluck('subject_id')),
        ));

        return $events->map(fn (AuditEvent $event) => $this->row($event, $organizations, $users))->values()->all();
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @param  Collection<int, User>  $users
     * @return array<string, mixed>
     */
    private function row(AuditEvent $event, Collection $organizations, Collection $users): array
    {
        $organization = $event->organization_id === null ? null : $organizations->get($event->organization_id);
        $subjectType = class_basename($event->subject_type);
        $local = $event->created_at->copy()->setTimezone(self::TIMEZONE);

        return [
            'id' => $event->id,
            'action' => $event->action,
            'action_label' => $this->label('admin.audit.actions.'.$event->action, $event->action),
            'at' => $event->created_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'at_label' => __('admin.audit.at', ['date' => $local->translatedFormat('j M Y'), 'time' => $local->format('H.i')]),
            'organization' => $event->organization_id === null ? null : [
                'id' => $event->organization_id,
                'name' => $organization?->name,
                'slug' => $organization?->slug,
            ],
            'actor' => [
                'type' => $event->actor_type,
                'type_label' => $this->label('admin.audit.actor_types.'.$event->actor_type, $event->actor_type),
                'id' => $event->actor_id,
                'name' => in_array($event->actor_type, self::USER_ACTORS, true) && ctype_digit((string) $event->actor_id)
                    ? $users->get((int) $event->actor_id)?->name
                    : null,
            ],
            'subject' => [
                'type' => $subjectType,
                'type_label' => $this->label('admin.audit.subject_types.'.$subjectType, $subjectType),
                'id' => $event->subject_id,
                'name' => $this->subjectName($event, $organizations, $users),
            ],
            'reason' => $event->reason,
            'changes' => $this->changes($event->before ?? [], $event->after ?? []),
            'ip' => $event->ip,
        ];
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @param  Collection<int, User>  $users
     */
    private function subjectName(AuditEvent $event, Collection $organizations, Collection $users): ?string
    {
        if (! ctype_digit($event->subject_id)) {
            return null;
        }

        return match ($event->subject_type) {
            (new Organization)->getMorphClass() => $organizations->get((int) $event->subject_id)?->name,
            (new User)->getMorphClass() => $users->get((int) $event->subject_id)?->name,
            default => null,
        };
    }

    /**
     * Each changed field with its value before and after, as display strings.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{field: string, before: ?string, after: ?string}>
     */
    private function changes(array $before, array $after): array
    {
        $before = AuditRedaction::strip($before);
        $after = AuditRedaction::strip($after);

        return collect(array_keys($before + $after))
            ->map(fn (string|int $field) => [
                'field' => (string) $field,
                'before' => array_key_exists($field, $before) ? $this->display($before[$field]) : null,
                'after' => array_key_exists($field, $after) ? $this->display($after[$field]) : null,
            ])
            ->values()
            ->all();
    }

    private function display(mixed $value): string
    {
        return is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return list<int>
     */
    private function ids(Collection $values): array
    {
        return $values->filter(fn ($id) => $id !== null && ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function label(string $key, string $fallback): string
    {
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $fallback;
    }
}
