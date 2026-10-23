<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F-009 A6: every table is tenant-scoped unless it is on this reviewed allowlist.
 * Adding a table without organization_id means adding it here, in review.
 */
class SchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_SELLER_OWNED = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'organizations',
        'password_reset_tokens',
        'sessions',
        'users',
    ];

    /**
     * Tables whose organization_id is nullable by design: audit_events also
     * records platform-level actions that belong to no organization (F-010,
     * ADR-0011). It still carries the foreign key and the leading index.
     */
    private const ORGANIZATION_OPTIONAL = [
        'audit_events',
    ];

    public function test_ac_a6_every_table_has_a_not_null_indexed_foreign_keyed_organization_id(): void
    {
        $tables = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->pluck('table_name')
            ->reject(fn (string $table) => in_array($table, self::NOT_SELLER_OWNED, true))
            ->values();

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $column = DB::selectOne(
                "select is_nullable from information_schema.columns where table_schema = 'public' and table_name = ? and column_name = 'organization_id'",
                [$table],
            );
            $this->assertNotNull($column, "Table [{$table}] has no organization_id. Add it, or allowlist the table in SchemaGuardTest.");

            if (! in_array($table, self::ORGANIZATION_OPTIONAL, true)) {
                $this->assertSame('NO', $column->is_nullable, "{$table}.organization_id must be NOT NULL.");
            }

            $this->assertTrue($this->hasForeignKeyToOrganizations($table), "{$table}.organization_id needs a foreign key to organizations.");
            $this->assertTrue($this->hasIndexLeadingWithOrganizationId($table), "{$table} needs an index whose first column is organization_id.");
        }
    }

    private function hasForeignKeyToOrganizations(string $table): bool
    {
        return DB::selectOne(<<<'SQL'
            select 1 from pg_constraint c
            join pg_class t on t.oid = c.conrelid
            join pg_class r on r.oid = c.confrelid
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f' and t.relname = ? and r.relname = 'organizations'
              and a.attname = 'organization_id' and array_length(c.conkey, 1) = 1
            SQL, [$table]) !== null;
    }

    private function hasIndexLeadingWithOrganizationId(string $table): bool
    {
        return DB::selectOne(<<<'SQL'
            select 1 from pg_index i
            join pg_class t on t.oid = i.indrelid
            join pg_attribute a on a.attrelid = t.oid and a.attnum = i.indkey[0]
            where t.relname = ? and a.attname = 'organization_id'
            SQL, [$table]) !== null;
    }
}
