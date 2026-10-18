<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Policies\AuditEventPolicy;
use App\Tenancy\BelongsToOrganization;
use App\Tenancy\OrganizationOwnedPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * F-009 A7: every model on a table with organization_id is scoped and has a policy.
 */
class ModelGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reviewed policies that deliberately do not extend OrganizationOwnedPolicy.
     */
    private const POLICY_EXCEPTIONS = [
        AuditEvent::class => AuditEventPolicy::class, // sellers never see audit events (F-009 Q7)
    ];

    public function test_ac_a7_tenant_models_use_the_scoping_trait_and_have_a_policy(): void
    {
        $checked = 0;

        foreach ($this->models() as $class) {
            $model = new $class;

            if (! Schema::hasColumn($model->getTable(), 'organization_id')) {
                continue;
            }

            $checked++;
            $this->assertContains(BelongsToOrganization::class, class_uses_recursive($class), "{$class} must use BelongsToOrganization.");
            $policy = Gate::getPolicyFor($class);
            $this->assertNotNull($policy, "{$class} needs a policy.");

            if (isset(self::POLICY_EXCEPTIONS[$class])) {
                $this->assertInstanceOf(self::POLICY_EXCEPTIONS[$class], $policy);
            } else {
                $this->assertInstanceOf(OrganizationOwnedPolicy::class, $policy, "{$class}'s policy must extend OrganizationOwnedPolicy (404 for other organizations).");
            }
            $this->assertNotContains('organization_id', $model->getFillable(), "{$class} must not make organization_id fillable.");
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_the_tenant_model_itself_has_a_policy(): void
    {
        // organizations has no organization_id, so the loop above skips it.
        $this->assertNotNull(Gate::getPolicyFor(Organization::class));
    }

    /**
     * @return list<class-string<Model>>
     */
    private function models(): array
    {
        $models = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            $class = 'App\\'.Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString();

            if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $models[] = $class;
            }
        }

        return $models;
    }
}
