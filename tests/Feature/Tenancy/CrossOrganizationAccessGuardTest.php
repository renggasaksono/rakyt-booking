<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * F-009 A8 / A8a: only reviewed system jobs (config/rakyt.php allowlist) and
 * admin query classes may bypass the organization scope.
 */
class CrossOrganizationAccessGuardTest extends TestCase
{
    private const BYPASS_PATTERN = '/acrossOrganizations\(|withoutGlobalScopes?\(/';

    public function test_ac_a8_scope_bypasses_outside_admin_queries_are_exactly_the_allowlist(): void
    {
        $adminPath = config('rakyt.admin_query_path');

        $bypassing = collect($this->filesBypassingTheScope())
            ->reject(fn (string $path) => str_starts_with($path, $adminPath.'/'))
            ->sort()
            ->values()
            ->all();

        $allowlist = collect(config('rakyt.cross_organization_allowlist'))->sort()->values()->all();

        $this->assertSame($allowlist, $bypassing, 'Scope bypass found outside the reviewed allowlist (config/rakyt.php), or an allowlisted file no longer needs it.');
    }

    /**
     * @return list<string>
     */
    private function filesBypassingTheScope(): array
    {
        $files = Finder::create()
            ->files()
            ->in(app_path())
            ->exclude('Tenancy') // the scope's own mechanism
            ->name('*.php');

        $paths = [];

        foreach ($files as $file) {
            if (preg_match(self::BYPASS_PATTERN, $file->getContents())) {
                $paths[] = 'app/'.Str::replace('\\', '/', $file->getRelativePathname());
            }
        }

        return $paths;
    }
}
