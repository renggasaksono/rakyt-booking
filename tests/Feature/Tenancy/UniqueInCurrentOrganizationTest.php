<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\Trip;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Rules\UniqueInCurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 R14: org-scoped uniqueness (first use: the trip slug).
 */
class UniqueInCurrentOrganizationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_r14_a_value_is_unique_per_organization_and_never_reveals_other_organizations(): void
    {
        [$a, $b] = $this->createTenants();
        $mine = $this->asTenant($a, fn () => Trip::factory()->create(['name' => 'Komodo', 'slug' => 'komodo']));
        $this->asTenant($b, fn () => Trip::factory()->create(['name' => 'Bromo', 'slug' => 'bromo']));

        $passes = fn (string $slug, ?Trip $ignore = null) => $this->asTenant($a, fn () => Validator::make(
            ['slug' => $slug],
            ['slug' => [new UniqueInCurrentOrganization(Trip::class, 'slug', $ignore)]],
        )->passes());

        $this->assertFalse($passes('komodo'));
        $this->assertTrue($passes('komodo', $mine));
        $this->assertTrue($passes('bromo'), "Another organization's slug is neither rejected nor revealed.");
    }

    public function test_r14_without_a_current_organization_every_value_counts_as_taken(): void
    {
        app(CurrentOrganization::class)->set(null);

        $this->assertTrue((new UniqueInCurrentOrganization(Trip::class, 'slug'))->isTaken('apa-saja'));
    }

    public function test_r14_only_organization_scoped_models_are_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UniqueInCurrentOrganization(Organization::class, 'public_id');
    }
}
