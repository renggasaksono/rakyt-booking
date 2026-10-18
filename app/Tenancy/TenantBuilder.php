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
 * organization_id; without one every row must name it. Inserts whose rows
 * cannot be checked (insert … select) are refused. Updates and increments
 * may never touch organization_id (F-009 A4).
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class TenantBuilder extends Builder
{
    public function insert(array $values)
    {
        return $this->toBase()->insert($this->withOrganization($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return $this->toBase()->insertGetId($this->withOrganization([$values])[0], $sequence);
    }

    public function insertOrIgnore(array $values)
    {
        return $this->toBase()->insertOrIgnore($this->withOrganization($values));
    }

    public function insertOrIgnoreReturning(array $values, ...$arguments)
    {
        return $this->toBase()->insertOrIgnoreReturning($this->withOrganization($values), ...$arguments);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $columns = $update ?? array_keys(reset($values) ?: []);
        $this->guardOrganizationId(array_map(fn ($key, $value) => is_int($key) ? $value : $key, array_keys($columns), $columns));

        return parent::upsert($this->withOrganization($values), $uniqueBy, $update);
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
        $rows = array_is_list($values) && is_array($values[0] ?? null) ? $values : [$values];
        $current = app(CurrentOrganization::class)->id();

        return array_map(function (array $row) use ($current) {
            if ($current !== null) {
                $row['organization_id'] = $current;
            } elseif (($row['organization_id'] ?? null) === null) {
                throw MissingOrganizationContext::forModel($this->getModel());
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
