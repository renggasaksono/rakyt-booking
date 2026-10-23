<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsAdmins;
use Tests\TestCase;

/**
 * F-010: TOTP two-factor is enforced for the admin account before any /admin
 * page is reachable, and the challenge is rate-limited.
 */
class AdminTwoFactorTest extends TestCase
{
    use BuildsAdmins, RefreshDatabase;

    public function test_ac_pending_admin_sees_the_challenge(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.two-factor.challenge'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/TwoFactorChallenge'));
    }

    public function test_ac_a_valid_code_grants_access_to_the_admin_area(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.two-factor.verify'), ['code' => $this->currentTwoFactorCode($admin)])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_ac_an_invalid_code_is_rejected_and_access_stays_blocked(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->from(route('admin.two-factor.challenge'))
            ->post(route('admin.two-factor.verify'), ['code' => '000000'])
            ->assertRedirect(route('admin.two-factor.challenge'))
            ->assertSessionHasErrors('code');

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.challenge'));
    }

    public function test_ac_a_confirmed_admin_skips_the_challenge(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)
            ->get(route('admin.two-factor.challenge'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_ac_the_challenge_is_rate_limited(): void
    {
        $admin = $this->platformAdmin();
        [$max] = config('rakyt.rate_limits.admin_two_factor.per_user');

        for ($i = 0; $i < $max; $i++) {
            $this->actingAs($admin)
                ->post(route('admin.two-factor.verify'), ['code' => '000000']);
        }

        $this->actingAs($admin)
            ->post(route('admin.two-factor.verify'), ['code' => '000000'])
            ->assertStatus(429);
    }
}
