<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\CurrentOrganization;
use App\Tenancy\ScopedToOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Tenancy\ProbeNote;
use Tests\Fixtures\Tenancy\RegistersProbeNotes;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 A8 (a): a job without a signed-in user receives an explicit organization and is scoped to it.
 */
class ScopedJobTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RegistersProbeNotes;

    public function test_ac_a8_job_middleware_scopes_to_the_given_organization_only(): void
    {
        $this->registerProbeNotes();
        [$a, $b] = $this->createTenants();
        $this->asTenant($a, fn () => ProbeNote::create(['title' => 'A']));
        $this->asTenant($b, fn () => ProbeNote::create(['title' => 'B']));
        app(CurrentOrganization::class)->set(null);

        $seen = (new ScopedToOrganization($a->id()))->handle(new \stdClass, fn () => ProbeNote::query()->pluck('title')->all());

        $this->assertSame(['A'], $seen);
        $this->assertNull(app(CurrentOrganization::class)->id(), 'Context is restored after the job.');
    }
}
