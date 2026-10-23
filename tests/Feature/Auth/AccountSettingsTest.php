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
 * F-001 A11, A12, F3, T6: Pengaturan → Akun.
 */
class AccountSettingsTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_f3_akun_is_its_own_page_with_email_and_password_in_the_seller_layout(): void
    {
        $tenant = $this->createTenant();

        $this->actingAsTenant($tenant)->get(route('settings.account'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Account')
                ->where('account.email', $tenant->owner->email)
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

        $this->assertSame('baru@example.com', $a->owner->refresh()->email);
        $this->assertSame($bEmail, $b->owner->refresh()->email);
        $this->assertSame($bHash, $b->owner->password);
    }

    public function test_guests_cannot_reach_account_settings(): void
    {
        $this->get(route('settings.account'))->assertRedirect(route('login'));
        $this->putJson(route('settings.account.password'), ['current_password' => 'x', 'password' => 'y'])->assertUnauthorized();
    }
}
