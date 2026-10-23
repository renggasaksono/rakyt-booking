<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\Auth\EmailChangedNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-001 A11, A12, A14, F3, T6: Pengaturan → Akun.
 */
class AccountSettingsTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_f3_akun_is_its_own_page_with_name_email_and_password_in_the_seller_layout(): void
    {
        $tenant = $this->createTenant();

        $this->actingAsTenant($tenant)->get(route('settings.account'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Account')
                ->where('account.name', $tenant->owner->name)
                ->where('account.email', $tenant->owner->email)
                ->where('urls.name', route('settings.account.name'))
                ->where('urls.email', route('settings.account.email'))
                ->where('urls.password', route('settings.account.password'))
                ->where('urls.back', route('settings.index'))
                ->missing('urls.logout')
                ->where('backLabel', '← Pengaturan')
                ->where('inApp', true)
                ->has('seller.nav', 6));

        $this->actingAsTenant($tenant)->get(route('settings.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Index')
                ->where('items.0.url', route('settings.profile'))
                ->where('items.1.url', route('settings.account'))
                ->where('urls.logout', route('logout')));

        // A6: "Keluar" is on Pengaturan (and the onboarding header), not repeated on Akun.
        $vue = file_get_contents(resource_path('js/pages/Settings/Account.vue'));
        $this->assertStringContainsString('inApp ? SellerLayout : BareFrame', $vue);
        $this->assertStringNotContainsString('logout', $vue);

        // Name first, then email, then password (A14).
        $name = strpos($vue, "t(copy, 'settings.name_section')");
        $email = strpos($vue, "t(copy, 'settings.email_section')");
        $password = strpos($vue, "t(copy, 'settings.password_section')");
        $this->assertNotFalse($name);
        $this->assertTrue($name < $email && $email < $password);
    }

    public function test_ac_a14_the_name_section_shows_the_current_name_in_an_editable_field(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Settings/Account.vue'));

        $this->assertMatchesRegularExpression('/useForm\(\{ name: props\.account\.name \}\)/', $vue);
        $this->assertMatchesRegularExpression('/<input\s+id="account-name"\s+v-model="nameForm\.name"\s+type="text"\s+autocomplete="name"/', $vue);
        $this->assertStringContainsString('<FieldError :message="nameForm.errors.name ?? nameForm.errors.rate_limit" />', $vue);

        $copy = __('account.settings');
        $this->assertSame('Nama Anda', $copy['name_section']);
        $this->assertSame('Simpan nama', $copy['save_name']);
        $this->assertSame('Nama disimpan.', $copy['name_changed']);
    }

    public function test_ac_a14_simpan_nama_is_ink_outline_and_the_page_has_no_gold_button(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Settings/Account.vue'));

        $this->assertMatchesRegularExpression('/class="[^"]*border-2 border-ink[^"]*"\s+:disabled="nameForm\.processing"\s*>\s*\{\{ t\(copy, \'settings\.save_name\'\) \}\}/', $vue);
        $this->assertStringNotContainsString('bg-prada', $vue);
    }

    public function test_ac_a14_saving_the_name_stores_it_trimmed_without_a_password_or_audit_event(): void
    {
        $tenant = $this->createTenant();
        $user = $tenant->owner;
        $hash = $user->password;
        $email = $user->email;

        $this->actingAsTenant($tenant)->put(route('settings.account.name'), ['name' => '  Sri Wahyuni  '])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast', 'Nama disimpan.');

        $user->refresh();
        $this->assertSame('Sri Wahyuni', $user->name);
        $this->assertSame($hash, $user->password);
        $this->assertSame($email, $user->email);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_ac_a14_the_name_is_validated_like_sign_up_and_errors_keep_the_input(): void
    {
        $tenant = $this->createTenant();
        $old = $tenant->owner->name;

        foreach (['', '   ', 'A', str_repeat('a', 81)] as $name) {
            $this->actingAsTenant($tenant)->from(route('settings.account'))
                ->put(route('settings.account.name'), ['name' => $name])
                ->assertRedirect(route('settings.account'))
                ->assertSessionHasErrors('name');
        }

        $this->actingAsTenant($tenant)->from(route('settings.account'))
            ->put(route('settings.account.name'), ['name' => 'A'])
            ->assertSessionHasErrors(['name' => 'Nama Anda minimal 2 karakter.']);

        $this->assertSame($old, $tenant->owner->refresh()->name);

        // The bounds are sign-up's (A2).
        $this->actingAsTenant($tenant)->put(route('settings.account.name'), ['name' => 'Al'])->assertSessionHasNoErrors();
        $this->actingAsTenant($tenant)->put(route('settings.account.name'), ['name' => str_repeat('a', 80)])->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('a', 80), $tenant->owner->refresh()->name);
    }

    public function test_ac_a14_the_new_name_greets_on_the_next_hari_ini_load_and_in_auth_mail(): void
    {
        Notification::fake();
        $tenant = $this->createTenant();

        $this->actingAsTenant($tenant)->put(route('settings.account.name'), ['name' => 'Sri Wahyuni'])->assertSessionHasNoErrors();

        $this->actingAsTenant($tenant)->get('/hari-ini')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard/Today')
                ->where('greeting.text', fn (string $text) => str_ends_with($text, ', Sri Wahyuni.')));

        $user = $tenant->owner->refresh();
        $this->assertSame('Halo Sri Wahyuni,', (new VerifyEmailNotification)->toMail($user)->greeting);
    }

    public function test_ac_a14_the_shared_account_prop_no_longer_carries_the_name(): void
    {
        $tenant = $this->createTenant();

        $this->actingAsTenant($tenant)->get('/hari-ini')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('account')
                ->missing('account.name'));
    }

    public function test_ac_a14_the_name_change_shares_the_account_change_limiter(): void
    {
        $route = app('router')->getRoutes()->getByName('settings.account.name');

        $this->assertSame('PUT', $route->methods()[0]);
        $this->assertContains('throttle:account-change', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_ac_f3_during_onboarding_akun_keeps_its_bare_frame(): void
    {
        $user = $this->signedUpUser();

        $this->actingAs($user)->get(route('settings.account'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Account')
                ->where('urls.back', route('onboarding'))
                ->where('inApp', false));

        $tenant = $this->onboardingTenant();

        $this->actingAsTenant($tenant)->get(route('settings.account'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('inApp', false));
    }

    public function test_ac_a11_changing_the_email_needs_the_current_password(): void
    {
        Notification::fake();
        $tenant = $this->createTenant();
        $old = $tenant->owner->email;

        $this->actingAsTenant($tenant)->put(route('settings.account.email'), ['email' => 'baru@example.com', 'current_password' => 'salah'])
            ->assertSessionHasErrors(['current_password' => 'Password salah.']);

        $this->assertSame($old, $tenant->owner->refresh()->email);
        $this->assertDatabaseCount('audit_events', 0);
        Notification::assertNothingSent();
    }

    public function test_ac_a11_a_new_email_is_saved_unverified_verified_again_and_the_old_address_is_told(): void
    {
        Notification::fake();
        $tenant = $this->createTenant();
        $user = $tenant->owner;
        $old = $user->email;
        $oldHash = $user->password;

        $this->actingAsTenant($tenant)->put(route('settings.account.email'), ['email' => ' Agus.Baru@Example.com ', 'current_password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast');

        $user->refresh();
        $this->assertSame('agus.baru@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        Notification::assertSentTo(new AnonymousNotifiable, EmailChangedNotification::class, function (EmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($old) {
            $this->assertSame($old, $notifiable->routes['mail']);
            $this->assertSame('ag***@example.com', $notification->maskedNewEmail);
            $this->assertStringContainsString('Email akun Rakyt Anda diganti ke ag***@example.com', implode(' ', $notification->toMail($notifiable)->introLines));

            return true;
        });

        $event = AuditEvent::acrossOrganizations()->where('action', 'auth.email_changed')->sole();
        $this->assertSame($tenant->id(), $event->organization_id);
        $this->assertEquals(['email' => $old], $event->before);
        $this->assertEquals(['email' => 'agus.baru@example.com'], $event->after);

        // ADR-0018: other devices are signed out.
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get(route('trips.index'))->assertRedirect(route('login'));
    }

    public function test_ac_a11_the_new_email_is_validated_like_sign_up(): void
    {
        $tenant = $this->createTenant();
        User::factory()->create(['email' => 'dipakai@example.com']);

        $this->actingAsTenant($tenant)->put(route('settings.account.email'), ['email' => 'DIPAKAI@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Masuk atau atur ulang password.']);
        $this->actingAsTenant($tenant)->put(route('settings.account.email'), ['email' => 'bukan-email', 'current_password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_ac_a12_changing_the_password_needs_the_current_one_and_ends_other_sessions(): void
    {
        $tenant = $this->createTenant();
        $user = $tenant->owner;
        $oldHash = $user->password;

        $this->actingAsTenant($tenant)->put(route('settings.account.password'), ['current_password' => 'salah', 'password' => 'baru12345'])
            ->assertSessionHasErrors(['current_password' => 'Password salah.']);
        $this->actingAsTenant($tenant)->put(route('settings.account.password'), ['current_password' => 'password', 'password' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->assertSame($oldHash, $user->refresh()->password);

        $this->actingAsTenant($tenant)->put(route('settings.account.password'), ['current_password' => 'password', 'password' => 'baru12345'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('baru12345', $user->refresh()->password));
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'auth.password_changed')->count());

        // This device keeps going; one with the old hash is signed out.
        $this->get(route('trips.index'))->assertOk();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get(route('trips.index'))->assertRedirect(route('login'));
    }

    public function test_ac_t6_account_changes_ignore_a_user_id_in_the_request(): void
    {
        Notification::fake();
        [$a, $b] = $this->createTenants();
        $bEmail = $b->owner->email;
        $bHash = $b->owner->password;

        $this->actingAsTenant($a)->put(route('settings.account.email'), ['email' => 'baru@example.com', 'current_password' => 'password', 'user_id' => $b->owner->id, 'id' => $b->owner->id]);
        $this->actingAsTenant($a)->put(route('settings.account.password'), ['current_password' => 'password', 'password' => 'baru12345', 'user_id' => $b->owner->id]);
        $bName = $b->owner->name;
        $this->actingAsTenant($a)->put(route('settings.account.name'), ['name' => 'Nama Baru', 'user_id' => $b->owner->id, 'id' => $b->owner->id]);

        $this->assertSame('baru@example.com', $a->owner->refresh()->email);
        $this->assertSame('Nama Baru', $a->owner->name);
        $this->assertSame($bEmail, $b->owner->refresh()->email);
        $this->assertSame($bName, $b->owner->name);
        $this->assertSame($bHash, $b->owner->password);
    }

    public function test_guests_cannot_reach_account_settings(): void
    {
        $this->get(route('settings.account'))->assertRedirect(route('login'));
        $this->putJson(route('settings.account.password'), ['current_password' => 'x', 'password' => 'y'])->assertUnauthorized();
        $this->putJson(route('settings.account.name'), ['name' => 'Siapa Saja'])->assertUnauthorized();
    }
}
