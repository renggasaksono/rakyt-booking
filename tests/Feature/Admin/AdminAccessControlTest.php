<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\BuildsAdmins;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-010: the server-side gate on every /admin route. Security does not depend
 * on hidden links — every route is tested for guest, seller and admin.
 */
class AdminAccessControlTest extends TestCase
{
    use BuildsAdmins, InteractsWithTenants, RefreshDatabase;

    /**
     * @return list<array{method: string, uri: string}>
     */
    private function adminRoutes(): array
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'admin.'))
            ->map(fn (Route $route) => [
                'method' => collect($route->methods())->first(fn (string $m) => $m !== 'HEAD'),
                'uri' => '/'.ltrim($route->uri(), '/'),
            ])
            ->values()
            ->all();

        $this->assertNotEmpty($routes, 'No admin routes found to authorize.');

        return $routes;
    }

    public function test_ac_guest_is_redirected_to_sign_in_from_every_admin_route(): void
    {
        foreach ($this->adminRoutes() as $route) {
            $this->call($route['method'], $route['uri'])
                ->assertRedirect(route('login'));
        }
    }

    public function test_ac_authenticated_seller_is_forbidden_from_every_admin_route(): void
    {
        $seller = $this->createTenant();

        foreach ($this->adminRoutes() as $route) {
            $this->actingAsTenant($seller)
                ->call($route['method'], $route['uri'])
                ->assertForbidden();
        }
    }

    public function test_ac_only_the_platform_admin_flag_opens_the_gate(): void
    {
        // A signed-in user without the flag cannot reach the admin area.
        $plain = $this->createTenant();
        $this->actingAsTenant($plain)->get(route('admin.dashboard'))->assertForbidden();

        // With the flag and a passed challenge, they can.
        $admin = $this->platformAdmin();
        $this->actingAsConfirmedAdmin($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_ac_admin_without_passing_two_factor_is_sent_to_the_challenge(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.two-factor.challenge'));
    }

    public function test_ac_admin_dashboard_is_never_cached(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)
            ->get(route('admin.dashboard'))
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_ac_admin_session_is_shorter_than_the_seller_session(): void
    {
        $this->assertLessThan(
            (int) config('session.lifetime'),
            (int) config('rakyt.admin.session_lifetime'),
            'The admin session must be shorter than the seller session (F-010 Security).',
        );
    }

    public function test_ac_admin_access_expires_after_the_idle_window(): void
    {
        $admin = $this->platformAdmin();
        $code = $this->currentTwoFactorCode($admin);

        // Pass the challenge, then reach the dashboard.
        $this->actingAs($admin)
            ->post(route('admin.two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        // After idling past the admin window, the challenge is required again.
        $this->travel((int) config('rakyt.admin.session_lifetime') + 1)->minutes();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.challenge'));
    }

    public function test_ac_admin_area_is_not_linked_from_the_seller_ui(): void
    {
        // A seller never receives the admin shell nav in their shared props.
        $seller = $this->createTenant();

        $this->actingAsTenant($seller)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('admin', null));
    }
}
