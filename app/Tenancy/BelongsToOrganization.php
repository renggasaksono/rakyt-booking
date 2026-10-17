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
 *   can never be mass-assigned or changed afterwards (A4).
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model) {
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
     * A non-numeric id in the URL is a 404, not a PostgreSQL type error (500).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        if ($field === $this->getKeyName() && $this->getKeyType() === 'int' && ! ctype_digit((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
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
