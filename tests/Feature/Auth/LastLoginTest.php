<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-012 "last activity": `users.last_login_at` is the time of the last
 * successful sign-in — login form, sign-up, password reset or remember-me
 * (ADR-0039) — null until the first one.
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

    public function test_sign_up_records_the_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-27 03:04:05', 'UTC'));

        $this->post(route('register'), ['name' => 'Agus Santoso', 'email' => 'agus@example.com', 'password' => 'rahasia123', 'terms' => true])
            ->assertRedirect();

        $user = User::query()->where('email', 'agus@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('2026-10-27 03:04:05', $user->last_login_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_password_reset_signs_in_and_records_the_time(): void
    {
        $seller = $this->createTenant();
        $seller->owner->forceFill(['email' => 'agus@example.com'])->save();
        $token = Password::broker()->createToken($seller->owner);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 03:04:05', 'UTC'));

        $this->post(route('password.store'), ['token' => $token, 'email' => 'agus@example.com', 'password' => 'baru12345'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($seller->owner);
        $this->assertSame('2026-10-27 03:04:05', $seller->owner->refresh()->last_login_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_remember_me_sign_in_records_the_time(): void
    {
        $seller = $this->createTenant();
        $owner = $seller->owner;
        $owner->forceFill(['remember_token' => 'ingat-saya-token'])->save();
        $this->travelTo(CarbonImmutable::parse('2026-10-27 03:04:05', 'UTC'));

        // A fresh session with only the recaller cookie, as after the browser restarts.
        $this->withCookie(Auth::guard('web')->getRecallerName(), $owner->id.'|ingat-saya-token|'.$owner->getAuthPassword())
            ->get('/hari-ini')
            ->assertOk();

        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(Auth::guard('web')->viaRemember());
        $this->assertSame('2026-10-27 03:04:05', $owner->refresh()->last_login_at->utc()->format('Y-m-d H:i:s'));
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
