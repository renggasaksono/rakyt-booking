<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\BulkWriteBypassesTenancy;
use App\Tenancy\Exceptions\MissingOrganizationContext;
use App\Tenancy\Exceptions\OrganizationIdImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder for organization-scoped models (ADR-0021).
 *
 * Bulk inserts and upserts skip model events, so they get the same rule as
 * the `creating` hook here: with a current organization every row gets its
 * organization_id; without one every row must name it. Updates and
 * increments may never touch organization_id (F-009 A4).
 *
 * Writes the scope cannot reach are refused: insert … select, upserts whose
 * conflict target lacks organization_id (ON CONFLICT ignores the scope and
 * would hit another organization's row), updateOrInsert / updateFrom
 * (forwarded unscoped to the base query) and truncate. Use the model
 * methods (create, updateOrCreate, save) instead.
 *
 * forceDelete() and "remove all scopes" keep the organization scope; only
 * acrossOrganizations() (allowlisted, A8) removes it.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class TenantBuilder extends Builder
{
    public function insert(array $values)
    {
        if ($values === []) {
            return true;
        }

        return $this->toBase()->insert($this->withOrganization($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return $this->toBase()->insertGetId($this->withOrganization([$values])[0], $sequence);
    }

    public function insertOrIgnore(array $values)
    {
        if ($values === []) {
            return 0;
        }

        return $this->toBase()->insertOrIgnore($this->withOrganization($values));
    }

    public function insertOrIgnoreReturning(array $values, ...$arguments)
    {
        return $this->toBase()->insertOrIgnoreReturning($this->withOrganization($values), ...$arguments);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if ($values === []) {
            return 0;
        }

        if (! in_array('organization_id', (array) $uniqueBy, true)) {
            throw BulkWriteBypassesTenancy::for('upsert without organization_id in $uniqueBy');
        }

        if ($update !== null) {
            $this->guardOrganizationId(array_map(
                fn ($key, $value) => is_int($key) ? $value : $key,
                array_keys($update),
                $update,
            ));
        }

        $rows = $this->withOrganization($values);

        // Laravel's default updates every column; never organization_id.
        $update ??= array_values(array_diff(array_keys($rows[0]), ['organization_id']));

        return parent::upsert($rows, $uniqueBy, $update);
    }

    public function updateOrInsert(array $attributes, array|callable $values = []): never
    {
        throw BulkWriteBypassesTenancy::for('updateOrInsert');
    }

    public function updateFrom(array $values): never
    {
        throw BulkWriteBypassesTenancy::for('updateFrom');
    }

    /**
     * Laravel's forceDelete() runs on the base query before any global scope is applied.
     */
    public function forceDelete()
    {
        return $this->withoutGlobalScopesExcept([OrganizationScope::class])->toBase()->delete();
    }

    /**
     * Without arguments Laravel removes every scope (Relation::rawUpdate() and touch() do this);
     * keep the organization's. Removing it by name is the allowlisted acrossOrganizations() path.
     */
    public function withoutGlobalScopes(?array $scopes = null)
    {
        if ($scopes === null) {
            $scopes = array_values(array_diff(array_keys($this->scopes), [OrganizationScope::class]));
        }

        return parent::withoutGlobalScopes($scopes);
    }

    public function truncate(): never
    {
        throw BulkWriteBypassesTenancy::for('truncate');
    }

    public function insertUsing(array $columns, $query): never
    {
        throw BulkWriteBypassesTenancy::for('insertUsing');
    }

    public function insertOrIgnoreUsing(array $columns, $query): never
    {
        throw BulkWriteBypassesTenancy::for('insertOrIgnoreUsing');
    }

    public function update(array $values)
    {
        $this->guardOrganizationId(array_keys($values));

        return parent::update($values);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->guardOrganizationId([$column, ...array_keys($extra)]);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->guardOrganizationId([$column, ...array_keys($extra)]);

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->guardOrganizationId([...array_keys($columns), ...array_keys($extra)]);

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->guardOrganizationId([...array_keys($columns), ...array_keys($extra)]);

        return parent::decrementEach($columns, $extra);
    }

    /**
     * @param  array<array-key, mixed>  $values  one row or a list of rows
     * @return list<array<string, mixed>>
     */
    private function withOrganization(array $values): array
    {
        // One associative row or a list of rows, as Laravel accepts.
        $rows = array_is_list($values) && is_array($values[0] ?? null) ? $values : [$values];
        $current = app(CurrentOrganization::class)->id();
        $model = $this->getModel();

        // A platform-level record (F-010) intentionally has no organization,
        // whatever the context: never stamp it, and never demand one.
        if ($model instanceof OrganizationOptional && $model->belongsToNoOrganization()) {
            return array_map(function (array $row) {
                $row['organization_id'] = $row['organization_id'] ?? null;

                return $row;
            }, $rows);
        }

        return array_map(function (array $row) use ($current, $model) {
            if ($current !== null) {
                $row['organization_id'] = $current;
            } elseif (($row['organization_id'] ?? null) === null) {
                throw MissingOrganizationContext::forModel($model);
            }

            return $row;
        }, $rows);
    }

    /**
     * @param  list<mixed>  $columns
     */
    private function guardOrganizationId(array $columns): void
    {
        foreach ($columns as $column) {
            if (is_string($column) && preg_match('/(^|\.)organization_id$/', $column)) {
                throw OrganizationIdImmutable::forModel($this->getModel());
            }
        }
    }
}
