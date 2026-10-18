<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-001 A10: verification is sent but never blocks anything in P0.
 */
class EmailVerificationTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    private function link(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_ac_a10_unverified_sellers_use_every_seller_feature_and_see_the_banner(): void
    {
        $tenant = $this->createTenant();
        $tenant->owner->forceFill(['email_verified_at' => null])->save();

        $this->actingAsTenant($tenant)->get(route('trips.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('account.verified', false)
                ->where('account.copy.banner', 'Cek email Anda untuk verifikasi alamat email.')
                ->where('account.copy.resend', 'Kirim ulang')
                ->where('account.urls.resend', route('verification.send')));

        $this->actingAsTenant($tenant)->get(route('settings.profile'))->assertOk();
        $this->actingAs($this->signedUpUser())->get(route('onboarding.organization'))->assertOk();
    }

    public function test_ac_a10_kirim_ulang_resends_and_is_throttled(): void
    {
        Notification::fake();
        $user = $this->signedUpUser();
        [$max] = config('rakyt.rate_limits.verification_resend.per_user');

        for ($i = 0; $i < $max; $i++) {
            $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('toast');
        }

        Notification::assertSentTimes(VerifyEmailNotification::class, $max);
        $this->actingAs($user)->withHeader('X-Inertia', 'true')->post(route('verification.send'))->assertSessionHasErrors('rate_limit');
        Notification::assertSentTimes(VerifyEmailNotification::class, $max);
    }

    public function test_ac_a10_the_signed_link_verifies_and_hides_the_banner(): void
    {
        $user = $this->signedUpUser();

        $this->get($this->link($user))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/VerifyEmail')->where('verified', true));

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->actingAs($user)->get(route('onboarding.organization'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('account.verified', true));
    }

    public function test_ac_a10_the_link_expires_after_60_minutes_and_cannot_be_tampered_with(): void
    {
        $user = $this->signedUpUser();
        $link = $this->link($user);

        $this->get($link.'x')->assertForbidden();
        $this->get(str_replace('/'.$user->id.'/', '/'.($user->id + 1).'/', $link))->assertForbidden();

        $this->travel(61)->minutes();
        $this->get($link)->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('verified', false));

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_ac_a10_the_mail_link_is_signed_and_valid_for_60_minutes(): void
    {
        $user = $this->signedUpUser();
        $mail = (new VerifyEmailNotification)->toMail($user);

        $this->assertSame('Verifikasi email Anda', $mail->subject);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->getTimestamp(), (int) $query['expires'], 2);
    }
}
