<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-009 Data & rules: the current organization comes from the membership, never from input.
 */
class CurrentOrganizationResolutionTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->any('/whoami', fn () => [
            'organization_id' => app(CurrentOrganization::class)->id(),
        ]);
    }

    public function test_current_organization_is_resolved_from_the_membership(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->getJson('/whoami')->assertExactJson(['organization_id' => $a->id()]);
    }

    public function test_organization_id_in_query_body_or_header_is_ignored(): void
    {
        [$a, $b] = $this->createTenants();

        $this->actingAsTenant($a)
            ->withHeader('X-Organization-Id', (string) $b->id())
            ->postJson('/whoami?organization_id='.$b->id(), ['organization_id' => $b->id()])
            ->assertExactJson(['organization_id' => $a->id()]);
    }

    public function test_user_with_more_than_one_membership_gets_an_error_instead_of_a_guess(): void
    {
        [$a] = $this->createTenants();
        $second = Organization::factory()->create();
        $this->asTenant(new Tenant($second, $a->owner), fn () => OrganizationMember::factory()->create([
            'organization_id' => $second->id,
            'user_id' => $a->owner->id,
        ]));

        $this->actingAsTenant($a)->getJson('/whoami')->assertStatus(409);
        $this->actingAsTenant($a)->get('/whoami')->assertStatus(409)->assertSee('lebih dari satu usaha');
    }

    public function test_each_request_resets_the_current_organization(): void
    {
        [$a, $b] = $this->createTenants();

        $this->actingAsTenant($a)->getJson('/whoami')->assertExactJson(['organization_id' => $a->id()]);
        $this->actingAsTenant($b)->getJson('/whoami')->assertExactJson(['organization_id' => $b->id()]);
    }
}
