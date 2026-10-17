<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\BelongsToOrganization;
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
            $this->assertNotNull(Gate::getPolicyFor($class), "{$class} needs a policy.");
            $this->assertNotContains('organization_id', $model->getFillable(), "{$class} must not make organization_id fillable.");
        }

        $this->assertGreaterThan(0, $checked);
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
