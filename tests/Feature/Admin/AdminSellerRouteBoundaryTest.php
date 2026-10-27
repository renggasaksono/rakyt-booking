<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route as RouteFacade;
use LogicException;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-010, ADR-0011: the platform admin is not a tenant. Every seller route
 * (the `auth` routes outside /admin) refuses an admin, before or after TOTP:
 * page visits go to the admin area (whose gate requires TOTP), writes and JSON
 * get 403. The routes are iterated, so a new seller route is covered as soon
 * as it exists.
 */
class AdminSellerRouteBoundaryTest extends TestCase
{
    use BuildsAdmins, BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    /**
     * Signed-in routes outside /admin that the admin shares with sellers:
     * signing out (the TOTP challenge and the admin shell link to it).
     */
    private const SHARED_ROUTES = ['logout'];

    /**
     * @return list<array{method: string, uri: string}>
     */
    private function sellerRoutes(): array
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('auth', $route->gatherMiddleware(), true))
            ->reject(fn (Route $route) => str_starts_with((string) $route->getName(), 'admin.'))
            ->reject(fn (Route $route) => in_array($route->getName(), self::SHARED_ROUTES, true))
            ->map(fn (Route $route) => [
                'method' => collect($route->methods())->first(fn (string $m) => $m !== 'HEAD'),
                'uri' => '/'.ltrim($route->uri(), '/'),
            ])
            ->values()
            ->all();

        $this->assertNotEmpty($routes, 'No seller routes found to check.');

        return $routes;
    }

    /**
     * @return array<string, \Closure(User): static>
     */
    private function adminStates(): array
    {
        return [
            'pending TOTP' => fn (User $admin) => $this->actingAs($admin),
            'past TOTP' => fn (User $admin) => $this->actingAsConfirmedAdmin($admin),
        ];
    }

    public function test_ac_admin_is_sent_to_the_admin_area_from_every_seller_page(): void
    {
        $admin = $this->platformAdmin();

        foreach ($this->adminStates() as $state => $acting) {
            foreach ($this->sellerRoutes() as $route) {
                if ($route['method'] !== 'GET') {
                    continue;
                }

                $response = $acting($admin)->get($route['uri']);

                $this->assertSame(302, $response->getStatusCode(), "{$state}: GET {$route['uri']}");
                $this->assertSame(route('admin.dashboard'), $response->headers->get('Location'), "{$state}: GET {$route['uri']}");
            }
        }
    }

    public function test_ac_admin_is_forbidden_from_every_seller_write_and_json_request(): void
    {
        $admin = $this->platformAdmin();

        foreach ($this->adminStates() as $state => $acting) {
            foreach ($this->sellerRoutes() as $route) {
                $response = $acting($admin)->json($route['method'], $route['uri']);
                $this->assertSame(403, $response->getStatusCode(), "{$state}: {$route['method']} {$route['uri']} (JSON)");

                if ($route['method'] !== 'GET') {
                    $response = $acting($admin)->call($route['method'], $route['uri']);
                    $this->assertSame(403, $response->getStatusCode(), "{$state}: {$route['method']} {$route['uri']}");
                }
            }
        }
    }

    public function test_ac_admin_pending_totp_reaches_only_the_challenge(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('settings.account'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.challenge'));
    }

    public function test_ac_admin_cannot_become_a_tenant_through_onboarding(): void
    {
        $admin = $this->platformAdmin();

        foreach ($this->adminStates() as $acting) {
            $acting($admin)->post(route('onboarding.organization'), $this->organizationInput())->assertForbidden();
        }

        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, OrganizationMember::acrossOrganizations()->where('user_id', $admin->getKey())->count());
    }

    public function test_ac_admin_cannot_change_name_email_or_password_through_seller_account(): void
    {
        $admin = $this->platformAdmin(['name' => 'Admin Rakyt', 'email' => 'admin@example.com', 'password' => 'old-password-1']);

        foreach ($this->adminStates() as $acting) {
            $acting($admin)->put(route('settings.account.name'), ['name' => 'Bukan Admin'])->assertForbidden();
            $acting($admin)->put(route('settings.account.email'), ['email' => 'other@example.com', 'current_password' => 'old-password-1'])->assertForbidden();
            $acting($admin)->put(route('settings.account.password'), [
                'current_password' => 'old-password-1',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])->assertForbidden();
        }

        $admin->refresh();
        $this->assertSame('Admin Rakyt', $admin->name);
        $this->assertSame('admin@example.com', $admin->email);
        $this->assertTrue(Hash::check('old-password-1', $admin->password));
    }

    public function test_ac_an_admin_can_never_be_given_an_organization_membership(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();

        $this->expectException(LogicException::class);

        $this->asTenant($seller, fn () => OrganizationMember::create([
            'user_id' => $admin->getKey(),
            'role' => OrganizationMember::ROLE_OWNER,
        ]));
    }

    public function test_ac_grant_refuses_a_user_who_is_a_seller_member(): void
    {
        $seller = $this->createTenant();

        $this->artisan('admin:grant', ['email' => $seller->owner->email])
            ->expectsOutputToContain('member of an organization')
            ->assertFailed();

        $this->assertFalse($seller->owner->refresh()->is_platform_admin);
        $this->assertNull($seller->owner->two_factor_secret);
    }

    public function test_ac_sign_in_never_follows_a_stale_seller_url_for_an_admin(): void
    {
        $admin = $this->platformAdmin(['email' => 'admin@example.com', 'password' => 'admin-password-1']);

        // A guest visit to a seller page leaves it as the intended URL.
        $this->get(route('settings.account'))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => 'admin@example.com', 'password' => 'admin-password-1'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_ac_sign_in_keeps_an_intended_admin_url(): void
    {
        $admin = $this->platformAdmin(['email' => 'admin@example.com', 'password' => 'admin-password-1']);

        $this->get(route('admin.sellers.index'))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => 'admin@example.com', 'password' => 'admin-password-1'])
            ->assertRedirect(route('admin.sellers.index'));
    }

    public function test_ac_the_totp_challenge_never_follows_a_seller_url(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->withSession(['url.intended' => route('settings.account')])
            ->post(route('admin.two-factor.verify'), ['code' => $this->currentTwoFactorCode($admin)])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_ac_admin_can_still_sign_out(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_ac_sellers_still_reach_their_account_and_onboarding(): void
    {
        $seller = $this->createTenant();
        $this->actingAsTenant($seller)->get(route('settings.account'))->assertOk();
        $this->actingAsTenant($seller)->get(route('dashboard'))->assertOk();

        $newcomer = $this->signedUpUser();
        $this->actingAs($newcomer)->get(route('onboarding.organization'))->assertOk();
        $this->actingAs($newcomer)->post(route('onboarding.organization'), $this->organizationInput())
            ->assertRedirect(route('onboarding.trip'));
    }
}
