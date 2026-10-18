<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * F-001 A1–A3: "Daftar" with email + password (ADR-0012).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return ['name' => 'Agus Santoso', 'email' => 'agus@example.com', 'password' => 'rahasia123', 'terms' => true, ...$overrides];
    }

    public function test_ac_a1_sign_up_screen_shows_the_mockup_copy_and_fields(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Register')
                ->where('copy.register.headline', 'Trip pertama Anda online dalam 10 menit.')
                ->where('copy.register.name', 'Nama Anda')
                ->where('copy.register.email', 'Email')
                ->where('copy.register.password', 'Password')
                ->where('copy.register.password_hint', 'Minimal 8 karakter')
                ->where('copy.password_toggle.show', 'Lihat')
                ->where('copy.register.terms', 'Syarat')
                ->where('copy.register.privacy', 'Privasi')
                ->where('copy.register.free', 'Gratis untuk mulai.')
                ->where('copy.register.submit', 'Daftar')
                ->where('copy.register.have_account', 'Sudah punya akun?')
                ->where('urls.terms', route('legal.terms'))
                ->where('urls.privacy', route('legal.privacy'))
                ->where('urls.login', route('login'))
                ->missing('copy.register.password_confirmation'));

        // ADR-0019: no specific free-plan limit is promised.
        $this->assertStringNotContainsString('1 trip', __('account.register.free'));
        $this->get(route('legal.terms'))->assertOk();
        $this->get(route('legal.privacy'))->assertOk();
    }

    public function test_ac_a1_the_page_uses_the_right_input_types_and_autocomplete(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Auth/Register.vue'));

        $this->assertStringContainsString('type="email"', $vue);
        $this->assertStringContainsString('autocomplete="email"', $vue);
        $this->assertStringContainsString('autocomplete="new-password"', $vue);
        $this->assertStringNotContainsString('password_confirmation', $vue);
    }

    public function test_ac_a3_valid_sign_up_creates_one_user_signs_in_and_lands_on_step_1(): void
    {
        Notification::fake();
        $this->freezeTime();

        $this->post(route('register'), $this->input(['email' => '  Agus@Example.COM ']))
            ->assertRedirect(route('onboarding.organization'));

        $this->assertDatabaseCount('users', 1);
        $user = User::query()->first();
        $this->assertSame('agus@example.com', $user->email);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(now()->getTimestamp(), $user->signup_started_at->getTimestamp());
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        Notification::assertSentTimes(VerifyEmailNotification::class, 1);
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmailNotification);
    }

    public function test_ac_a3_a_double_tap_creates_one_user(): void
    {
        Notification::fake();

        $this->post(route('register'), $this->input())->assertRedirect(route('onboarding.organization'));
        // The second tap arrives signed in: it is sent on, not registered again.
        $this->post(route('register'), $this->input())->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_ac_a2_validation_messages_are_bahasa_and_keep_input_except_the_password(): void
    {
        $this->from(route('register'))
            ->post(route('register'), ['name' => 'A', 'email' => 'bukan-email', 'password' => 'pendek', 'terms' => false])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['name', 'email', 'password', 'terms'])
            ->assertSessionHasInput('name', 'A')
            ->assertSessionHasInput('email', 'bukan-email')
            ->assertSessionMissing('_old_input.password');

        $errors = session('errors');
        $this->assertSame('Email harus berupa alamat email yang valid.', $errors->first('email'));
        $this->assertSame('Centang persetujuan Syarat & Privasi untuk lanjut.', $errors->first('terms'));
        $this->assertStringContainsString('8', $errors->first('password'));
        $this->assertGuest();
    }

    public function test_ac_a2_name_is_2_to_80_characters_and_password_has_no_composition_rules(): void
    {
        $this->post(route('register'), $this->input(['name' => str_repeat('a', 81)]))->assertSessionHasErrors('name');
        $this->post(route('register'), $this->input(['name' => '  ']))->assertSessionHasErrors('name');

        Notification::fake();
        $this->post(route('register'), $this->input(['name' => 'Al', 'password' => 'aaaaaaaa']))->assertSessionHasNoErrors();
    }

    public function test_ac_a2_a_registered_email_is_refused_case_insensitively_with_both_actions(): void
    {
        User::factory()->create(['email' => 'agus@example.com']);

        $this->post(route('register'), $this->input(['email' => 'AGUS@example.com']))
            ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Masuk atau atur ulang password.']);

        $this->assertDatabaseCount('users', 1);
        $this->get(route('register'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('urls.login', route('login'))
            ->where('urls.reset', route('password.request')));
    }

    public function test_sign_up_is_throttled_per_ip_and_keeps_the_input(): void
    {
        Notification::fake();
        config(['rakyt.rate_limits.signup.per_ip' => [1, 60]]);

        $this->post(route('register'), $this->input(['email' => 'satu@example.com']));
        auth()->logout();

        $this->withHeader('X-Inertia', 'true')
            ->from(route('register'))
            ->post(route('register'), $this->input(['email' => 'dua@example.com']))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('rate_limit')
            ->assertSessionHasInput('email', 'dua@example.com')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_signed_in_users_do_not_see_the_sign_up_screen(): void
    {
        $this->actingAs(User::factory()->create())->get(route('register'))->assertRedirect(route('dashboard'));
    }
}
