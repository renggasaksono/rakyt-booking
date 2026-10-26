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
     * Events about one record, newest first (F-013 booking detail).
     *
     * @return Collection<int, AuditEvent>
     */
    public function forSubject(Model $subject, int $limit = self::RELATED_LIMIT): Collection
    {
        return $this->newest()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->limit($limit)
            ->get();
    }

    /**
     * Every organization, for the organization filter.
     *
     * @return Collection<int, Organization>
     */
    public function organizations(): Collection
    {
        return Organization::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'slug']);
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
     * @return Builder<AuditEvent>
     */
    private function newest(): Builder
    {
        return AuditEvent::acrossOrganizations()
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
