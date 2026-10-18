<?php

namespace Tests\Feature\Onboarding;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * F-001 B7: a seller's link can never shadow an app route.
 */
class ReservedSlugsTest extends TestCase
{
    public function test_ac_b7_every_first_path_segment_of_every_web_route_is_reserved(): void
    {
        $reserved = config('rakyt.reserved_slugs');
        $segments = collect(RouteFacade::getRoutes()->getRoutes())
            ->map(fn (Route $route) => explode('/', trim($route->uri(), '/'))[0])
            ->filter(fn (string $segment) => $segment !== '' && ! str_starts_with($segment, '{'))
            ->unique()
            ->values();

        $this->assertNotEmpty($segments);
        $this->assertSame([], $segments->reject(fn (string $segment) => in_array($segment, $reserved, true))->values()->all(), 'Add these to config/rakyt.php → reserved_slugs.');
    }

    public function test_ac_b7_the_initial_list_from_the_spec_is_reserved(): void
    {
        foreach (['admin', 'api', 'masuk', 'daftar', 'dashboard', 'pengaturan', 'trip', 'mulai', 'syarat', 'privasi', 'rakyt', 'www', 'storage', 'null', 'test', 'demo'] as $slug) {
            $this->assertContains($slug, config('rakyt.reserved_slugs'));
        }
    }
}
