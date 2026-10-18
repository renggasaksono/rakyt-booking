<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-001 A7–A9, F-009 A15a and R4.
 */
class PasswordResetTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_a7_the_answer_is_the_same_whether_or_not_the_email_exists(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'agus@example.com']);

        $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'agus@example.com']);
        $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'siapa@example.com']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect(route('password.request'))
                ->assertSessionHas('status', 'Jika email ini terdaftar, kami kirim link untuk atur ulang password.')
                ->assertSessionHasNoErrors();
        }

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
    }

    public function test_ac_a7_a15a_timing_is_comparable_for_unknown_emails(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'agus@example.com']);
        User::factory()->create(['email' => 'warm@example.com']);
        // Warm both paths (first use of the broker, the hasher, the views).
        $this->post(route('password.email'), ['email' => 'warm@example.com']);
        $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        $time = function (string $email) {
            $start = hrtime(true);
            $this->post(route('password.email'), ['email' => $email]);

            return (hrtime(true) - $start) / 1e6;
        };

        $known = $time('agus@example.com');
        $unknown = $time('siapa@example.com');

        // Both pay for one password-hash; neither is several times the other.
        $this->assertLessThan(max(4 * $known, $known + 100), $unknown);
        $this->assertLessThan(max(4 * $unknown, $unknown + 100), $known);
    }

    public function test_ac_a7_reset_requests_are_throttled(): void
    {
        Notification::fake();
        [$max] = config('rakyt.rate_limits.password_reset.per_email');

        for ($i = 0; $i < $max; $i++) {
            $this->post(route('password.email'), ['email' => 'agus@example.com']);
        }

        $this->withHeader('X-Inertia', 'true')
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'Agus@example.com'])
            ->assertSessionHasErrors('rate_limit');
    }

    public function test_ac_a8_a_registered_email_gets_a_queued_bahasa_reset_link_valid_60_minutes(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'agus@example.com', 'name' => 'Agus']);

        $this->post(route('password.email'), ['email' => 'agus@example.com']);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertSame('Atur ulang password Rakyt', $mail->subject);
            $this->assertStringContainsString('token=', $mail->actionUrl);
            $this->assertStringStartsWith(route('password.reset'), $mail->actionUrl);
            $this->assertStringContainsString('60 menit', implode(' ', $mail->outroLines));

            return true;
        });

        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordNotification('x'));
        $this->assertSame(60, config('auth.passwords.users.expire'));
    }

    public function test_ac_a8_expired_used_or_tampered_links_say_link_sudah_tidak_berlaku(): void
    {
        $user = User::factory()->create(['email' => 'agus@example.com']);
        $token = Password::broker()->createToken($user);

        $show = fn (string $t) => $this->get(route('password.reset', ['token' => $t, 'email' => 'agus@example.com']));

        $show($token)->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ResetPassword')->where('valid', true));
        $show('tampered')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('valid', false)
            ->where('copy.reset.invalid', 'Link sudah tidak berlaku. Minta link baru.')
            ->where('urls.request', route('password.request')));

        // Used once…
        $this->post(route('password.store'), ['token' => $token, 'email' => 'agus@example.com', 'password' => 'baru12345'])->assertRedirect();
        Auth::logout();
        $show($token)->assertInertia(fn (AssertableInertia $page) => $page->where('valid', false));
        $this->post(route('password.store'), ['token' => $token, 'email' => 'agus@example.com', 'password' => 'lagi12345'])
            ->assertSessionHasErrors(['token' => 'Link sudah tidak berlaku. Minta link baru.']);

        // …or expired after 60 minutes.
        $fresh = Password::broker()->createToken($user->refresh());
        $this->travel(61)->minutes();
        $show($fresh)->assertInertia(fn (AssertableInertia $page) => $page->where('valid', false));
    }

    public function test_ac_a9_new_password_signs_in_ends_other_sessions_and_is_audited(): void
    {
        $tenant = $this->createTenant();
        $user = $tenant->owner;
        $user->forceFill(['remember_token' => 'old-remember'])->save();
        $oldHash = $user->password;
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => 'baru12345'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('baru12345', $user->password));
        $this->assertNotSame('old-remember', $user->remember_token);
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Password::broker()->tokenExists($user, $token));

        // R4: a guest's reset is audited against the user's organization.
        $event = AuditEvent::acrossOrganizations()->where('action', 'auth.password_reset')->sole();
        $this->assertSame($tenant->id(), $event->organization_id);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame((string) $user->id, $event->actor_id);
        $this->assertNull($event->after);

        // Another device still holding the old password hash is signed out.
        Auth::logout();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
            ->get(route('trips.index'))
            ->assertRedirect(route('login'));
    }

    public function test_ac_a9_reset_works_for_a_seller_without_an_organization_yet(): void
    {
        $user = $this->signedUpUser();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => 'baru12345'])
            ->assertRedirect(route('onboarding.organization'));

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_ac_a9_the_new_password_must_have_8_characters(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), ['token' => $token, 'email' => $user->email, 'password' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }
}
