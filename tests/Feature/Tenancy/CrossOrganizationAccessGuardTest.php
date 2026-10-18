<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * F-009 A8 / A8a: only reviewed system jobs (config/rakyt.php allowlist) and
 * admin query classes may read seller data without the organization scope.
 *
 * "Without the scope" covers Eloquent bypasses and the raw query builder
 * (DB::table, DB::select, …), which never applies the scope at all.
 */
class CrossOrganizationAccessGuardTest extends TestCase
{
    /**
     * Eloquent scope bypasses, unscoped model queries, and any database access
     * outside Eloquent: the DB facade (also aliased), the container ('db',
     * DatabaseManager) and injected connections. DB::transaction*,
     * beginTransaction, commit, rollBack and afterCommit stay allowed.
     *
     * A safety net, not a proof (ADR-0021): code review and the C1 audit
     * still check tenant scoping.
     */
    private const BYPASS_PATTERN = '/'
        .'acrossOrganizations\(|withoutGlobalScopes?\(|newQueryWithoutScopes?\(|newModelQuery\('
        .'|->toBase\(|->getQuery\(|->getConnection\(|->getPdo\('
        .'|DB::(?!transaction|transactionLevel\(|beginTransaction\(|commit\(|rollBack\(|afterCommit\()\w+\('
        .'|\b(app|resolve)\(\s*[\'"](db|db\.connection)[\'"]\s*\)|DatabaseManager'
        .'|->make\(\s*[\'"]db(\.connection)?[\'"]|App::make\(\s*[\'"]db|\[\s*[\'"]db(\.connection)?[\'"]\s*\]'
        .'|\bresolveConnection\(|ConnectionInterface|ConnectionResolverInterface|Illuminate\\\\Database\\\\Connection\b'
        .'|Facades\\\\DB\s+as\s'
        .'/';

    /** The scope's own mechanism; reviewed with ADR-0021. */
    private const MECHANISM = [
        'app/Tenancy/BelongsToOrganization.php',
        'app/Tenancy/Middleware/ResolveCurrentOrganization.php',
        'app/Tenancy/Rules/ExistsInCurrentOrganization.php',
        'app/Tenancy/TenantBuilder.php',
    ];

    private const SCANNED = ['app', 'routes'];

    public function test_ac_a8_scope_bypasses_outside_admin_queries_are_exactly_the_allowlist(): void
    {
        $adminPath = config('rakyt.admin_query_path');

        $bypassing = collect($this->filesBypassingTheScope())
            ->reject(fn (string $path) => str_starts_with($path, $adminPath.'/'))
            ->reject(fn (string $path) => in_array($path, self::MECHANISM, true))
            ->sort()
            ->values()
            ->all();

        $allowlist = collect(config('rakyt.cross_organization_allowlist'))->sort()->values()->all();

        $this->assertSame($allowlist, $bypassing, 'Scope bypass or raw query found outside the reviewed allowlist (config/rakyt.php), or an allowlisted file no longer needs it.');
    }

    public function test_the_guard_detects_raw_queries_and_bypasses(): void
    {
        foreach ([
            'Trip::acrossOrganizations()->get();',
            'Trip::withoutGlobalScope(OrganizationScope::class);',
            "DB::table('trips')->get();",
            "DB::select('select * from bookings');",
            'Trip::query()->toBase()->get();',
            '(new Trip)->newModelQuery()->count();',
            '(new Trip)->newQueryWithoutScopes()->get();',
            '(new Trip)->newQueryWithoutScope(OrganizationScope::class);',
            "DB::connection('pgsql')->table('trips');",
            "DB::scalar('select count(*) from trips');",
            "DB::cursor('select * from trips');",
            '$trip->getConnection()->table(\'trips\');',
            "app('db')->table('trips');",
            "resolve('db')->select('x');",
            'app(DatabaseManager::class)->table(\'trips\');',
            'use Illuminate\\Support\\Facades\\DB as Database;',
            "app()->make('db')->table('trips');",
            "App::make('db')->table('trips');",
            "\$this->app['db']->table('trips');",
            'public function __construct(private ConnectionInterface $db) {}',
            'public function __construct(ConnectionResolverInterface $resolver) {}',
            'public function handle(\\Illuminate\\Database\\Connection $db) {}',
            'Trip::resolveConnection()->table(\'trips\');',
        ] as $code) {
            $this->assertMatchesRegularExpression(self::BYPASS_PATTERN, $code);
        }

        foreach ([
            'DB::transaction(fn () => 1);',
            'DB::transactionLevel();',
            'DB::afterCommit(fn () => 1);',
            'Trip::query()->where(\'x\', 1)->get();',
        ] as $code) {
            $this->assertDoesNotMatchRegularExpression(self::BYPASS_PATTERN, $code);
        }
    }

    /**
     * F-009 R14: `exists:` / `unique:` and Rule::exists / Rule::unique are not
     * organization-scoped. Use ExistsInCurrentOrganization / UniqueInCurrentOrganization.
     */
    private const BARE_RULE_PATTERN = '/[\'"|](exists|unique):|Rule::(exists|unique)\(/';

    public function test_r14_no_bare_exists_or_unique_rules(): void
    {
        $offending = [];

        foreach (self::SCANNED as $directory) {
            foreach (Finder::create()->files()->in(base_path($directory))->name('*.php') as $file) {
                if (preg_match(self::BARE_RULE_PATTERN, $file->getContents())) {
                    $offending[] = $directory.'/'.$file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offending, 'Use ExistsInCurrentOrganization / UniqueInCurrentOrganization instead of bare exists/unique rules.');
    }

    public function test_r14_the_bare_rule_guard_detects_the_patterns(): void
    {
        foreach (["'trip_id' => 'required|exists:trips,id'", "['exists:trips,id']", '"unique:trips,slug"', 'Rule::exists(\'trips\')', 'Rule::unique(\'trips\')'] as $code) {
            $this->assertMatchesRegularExpression(self::BARE_RULE_PATTERN, $code);
        }

        $this->assertDoesNotMatchRegularExpression(self::BARE_RULE_PATTERN, 'new ExistsInCurrentOrganization(\'trips\')');
    }

    public function test_mechanism_files_exist(): void
    {
        foreach (self::MECHANISM as $path) {
            $this->assertFileExists(base_path($path), 'Update MECHANISM when tenancy files move.');
        }
    }

    /**
     * @return list<string>
     */
    private function filesBypassingTheScope(): array
    {
        $paths = [];

        foreach (self::SCANNED as $directory) {
            foreach (Finder::create()->files()->in(base_path($directory))->name('*.php') as $file) {
                if (preg_match(self::BYPASS_PATTERN, $file->getContents())) {
                    $paths[] = $directory.'/'.Str::replace('\\', '/', $file->getRelativePathname());
                }
            }
        }

        return $paths;
    }
}
