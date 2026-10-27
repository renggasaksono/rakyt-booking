<?php

namespace App\Admin\Queries;

use App\Admin\AuditEventFilters;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Cross-organization reads of the audit trail for the platform admin (F-015,
 * ADR-0011). The only place audit events are read across organizations; the
 * seller app never reads them (F-009 Q7, F-015 AC-6). F-012's organization
 * detail and F-013's booking detail use forOrganization() / forSubject().
 */
class AuditEventQueries
{
    public const RELATED_LIMIT = 20;

    /**
     * One page of the admin audit view, newest first. Returns up to $limit
     * rows; ask for one more than a page to know whether a next page exists.
     *
     * @return Collection<int, AuditEvent>
     */
    public function search(AuditEventFilters $filters, int $offset, int $limit): Collection
    {
        return $filters->apply($this->newest())
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Events of one organization, newest first (F-012 organization detail).
     *
     * @return Collection<int, AuditEvent>
     */
    public function forOrganization(Organization $organization, int $limit = self::RELATED_LIMIT): Collection
    {
        return $this->newest()
            ->where('organization_id', $organization->getKey())
            ->limit($limit)
            ->get();
    }

    /**
     * Events about one record, newest first (F-013 booking detail). A
     * seller-owned subject (and an organization) is also matched on its
     * organization, so the query uses the `(organization_id, subject_type,
     * subject_id)` index; any other subject (a user) uses `(subject_type,
     * subject_id)`.
     *
     * @return Collection<int, AuditEvent>
     */
    public function forSubject(Model $subject, int $limit = self::RELATED_LIMIT): Collection
    {
        $organizationId = $this->organizationIdOf($subject);

        return $this->newest()
            ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->limit($limit)
            ->get();
    }

    /**
     * Whether an organization exists, to validate the organization filter
     * without loading every organization.
     */
    public function organizationExists(int $id): bool
    {
        return Organization::query()->whereKey($id)->exists();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Organization>
     */
    public function organizationsById(array $ids): Collection
    {
        return Organization::query()->whereKey($ids)->get(['id', 'name', 'slug'])->keyBy('id');
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function usersById(array $ids): Collection
    {
        return User::query()->whereKey($ids)->get(['id', 'name'])->keyBy('id');
    }

    /**
     * The organization an event about $subject is stored under, when it is
     * fixed by the subject itself (as AuditLog resolves it). A user's events
     * may belong to their organization or to none (F-010), so none is assumed.
     */
    private function organizationIdOf(Model $subject): ?int
    {
        $id = match (true) {
            $subject instanceof Organization => $subject->getKey(),
            $subject instanceof User => null,
            array_key_exists('organization_id', $subject->getAttributes()) => $subject->getAttribute('organization_id'),
            default => null,
        };

        return $id === null ? null : (int) $id;
    }

    /**
     * Newest first; the `(created_at, id)` index serves the unfiltered and
     * actor/action/platform views.
     *
     * @return Builder<AuditEvent>
     */
    private function newest(): Builder
    {
        return AuditEvent::acrossOrganizations()
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
