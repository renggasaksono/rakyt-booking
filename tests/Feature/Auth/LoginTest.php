<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-001 A4–A6, F-009 A14b: "Masuk" and "Keluar".
 */
class LoginTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_a4_sign_in_screen_has_the_fields_and_links(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('copy.login.remember', 'Ingat saya')
                ->where('copy.login.submit', 'Masuk')
                ->where('copy.login.forgot', 'Lupa password?')
                ->where('copy.login.no_account', 'Belum punya akun?')
                ->where('urls.forgot', route('password.request'))
                ->where('urls.register', route('register')));

        $vue = file_get_contents(resource_path('js/pages/Auth/Login.vue'));
        $this->assertStringContainsString('remember: true', $vue, '"Ingat saya" is checked by default.');
        $this->assertStringContainsString('autocomplete="current-password"', $vue);
    }

    public function test_ac_a4_wrong_email_and_wrong_password_get_the_same_message(): void
    {
        User::factory()->create(['email' => 'agus@example.com']);

        $wrongPassword = $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'salah-salah'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $unknownEmail = $this->post(route('login'), ['email' => 'tidak-ada@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertSame($wrongPassword->getStatusCode(), $unknownEmail->getStatusCode());
        $this->assertGuest();
    }

    public function test_ac_a4_sign_in_is_throttled_per_email_and_ip_with_the_form_message(): void
    {
        User::factory()->create(['email' => 'agus@example.com']);
        [$max] = config('rakyt.rate_limits.seller_login.per_email_ip');

        for ($i = 0; $i < $max; $i++) {
            $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'salah']);
        }

        // F-009 A14b: the form shows the message and keeps the email.
        $this->withHeader('X-Inertia', 'true')
            ->from(route('login'))
            ->post(route('login'), ['email' => ' AGUS@example.com', 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['rate_limit' => 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.'])
            ->assertSessionHasInput('email', 'AGUS@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_ac_a5_sign_in_regenerates_the_session_and_lands_per_e1_to_e3(): void
    {
        $user = $this->signedUpUser(['email' => 'agus@example.com']);
        $this->startSession();
        $before = session()->getId();

        $this->post(route('login'), ['email' => 'Agus@Example.com', 'password' => 'password'])
            ->assertRedirect(route('onboarding.organization'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());

        Auth::logout();
        $tenant = $this->createTenant();
        $this->post(route('login'), ['email' => $tenant->owner->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_ac_a5_remember_me_sets_a_long_lived_cookie_and_without_it_the_session_ends_with_the_browser(): void
    {
        User::factory()->create(['email' => 'agus@example.com']);
        $recaller = Auth::guard('web')->getRecallerName();

        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'password', 'remember' => true])
            ->assertCookie($recaller);

        Auth::logout();
        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'password', 'remember' => false])
            ->assertCookieMissing($recaller);

        $this->assertTrue(config('session.expire_on_close'));
        $this->assertGreaterThan(0, config('session.lifetime'));
    }

    public function test_ac_a6_keluar_invalidates_the_session_and_the_remember_me_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame('old-token', $user->refresh()->remember_token);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
