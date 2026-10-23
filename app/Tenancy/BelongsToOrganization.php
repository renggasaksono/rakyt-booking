<?php

namespace App\Tenancy;

use App\Models\Organization;
use App\Tenancy\Exceptions\MissingOrganizationContext;
use App\Tenancy\Exceptions\OrganizationIdImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Required on every model whose table has `organization_id` (F-009 A7).
 *
 * - Queries are scoped to the current organization (A1).
 * - `organization_id` is filled from the current organization on create and
 *   can never be mass-assigned or changed afterwards (A4), including through
 *   query-builder updates and bulk inserts (TenantBuilder).
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model) {
            // A platform-level record (F-010) intentionally has no organization,
            // whatever the current context: never stamp it with one.
            if ($model instanceof OrganizationOptional && $model->belongsToNoOrganization()) {
                $model->setAttribute('organization_id', null);

                return;
            }

            $current = app(CurrentOrganization::class)->id();

            if ($current !== null) {
                $model->setAttribute('organization_id', $current);

                return;
            }

            // System jobs without a current organization must name it explicitly (A8a).
            if ($model->getAttribute('organization_id') === null) {
                throw MissingOrganizationContext::forModel($model);
            }
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('organization_id')) {
                throw OrganizationIdImmutable::forModel($model);
            }
        });
    }

    public function initializeBelongsToOrganization(): void
    {
        $this->mergeGuarded(['organization_id']);
    }

    /**
     * Saves, deletes and reloads by key also match the organization, so a model
     * built by hand or hydrated from an unscoped query cannot touch another
     * organization's row (refresh, fresh, update, delete, touch, restore).
     *
     * @param  Builder<static>|\Illuminate\Database\Query\Builder  $query
     */
    protected function setKeysForSaveQuery($query)
    {
        return parent::setKeysForSaveQuery($query)
            ->where($this->qualifyColumn('organization_id'), $this->organizationIdForKeyQuery());
    }

    /**
     * @param  Builder<static>|\Illuminate\Database\Query\Builder  $query
     */
    protected function setKeysForSelectQuery($query)
    {
        return parent::setKeysForSelectQuery($query)
            ->where($this->qualifyColumn('organization_id'), $this->organizationIdForKeyQuery());
    }

    /**
     * The current organization; without one (system jobs), the organization the row was loaded with.
     */
    private function organizationIdForKeyQuery(): mixed
    {
        return app(CurrentOrganization::class)->id() ?? $this->getOriginal('organization_id');
    }

    /**
     * Applies the organization rule to bulk inserts/upserts; refuses mass updates of organization_id.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return TenantBuilder<static>
     */
    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
    }

    /**
     * A non-numeric or out-of-range id in the URL is a 404, not a PostgreSQL error (500).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (! RouteKey::isAcceptable($this, $value, $field)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * Same for withTrashed() routes (F-009 R15).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveSoftDeletableRouteBinding($value, $field = null): ?Model
    {
        if (! RouteKey::isAcceptable($this, $value, $field)) {
            return null;
        }

        return parent::resolveSoftDeletableRouteBinding($value, $field);
    }

    /**
     * Same for scopeBindings() child routes, checked against the child model (F-009 R16).
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        if (! RouteKey::isAcceptable($this->childRouteBindingModel($childType), $value, $field)) {
            return null;
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    /**
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveSoftDeletableChildRouteBinding($childType, $value, $field): ?Model
    {
        if (! RouteKey::isAcceptable($this->childRouteBindingModel($childType), $value, $field)) {
            return null;
        }

        return parent::resolveSoftDeletableChildRouteBinding($childType, $value, $field);
    }

    private function childRouteBindingModel(string $childType): Model
    {
        return $this->{$this->childRouteBindingRelationshipName($childType)}()->getRelated();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query across every organization. Only allowed in files on the reviewed
     * allowlist (config/rakyt.php) or in admin query classes; a test enforces it (A8, A8a).
     */
    public static function acrossOrganizations(): Builder
    {
        return static::query()->withoutGlobalScope(OrganizationScope::class);
    }
}
