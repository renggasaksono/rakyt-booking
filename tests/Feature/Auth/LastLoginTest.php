<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-012 "last activity": `users.last_login_at` is the time of the last
 * successful sign-in, null until the first one.
 */
class LastLoginTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_a_new_user_has_never_signed_in(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);
        $this->assertNull(User::query()->findOrFail($user->id)->last_login_at);
    }

    public function test_a_successful_sign_in_records_the_time(): void
    {
        $seller = $this->createTenant();
        $seller->owner->forceFill(['email' => 'agus@example.com'])->save();
        $this->travelTo(CarbonImmutable::parse('2026-10-27 03:04:05', 'UTC'));

        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($seller->owner);
        $this->assertSame('2026-10-27 03:04:05', $seller->owner->refresh()->last_login_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_failed_sign_in_records_nothing(): void
    {
        $user = User::factory()->create(['email' => 'agus@example.com']);

        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'salah-salah'])->assertSessionHasErrors('email');

        $this->assertNull($user->refresh()->last_login_at);
    }

    public function test_the_sign_in_time_is_not_mass_assignable(): void
    {
        $this->assertNotContains('last_login_at', (new User)->getFillable());
    }
}
