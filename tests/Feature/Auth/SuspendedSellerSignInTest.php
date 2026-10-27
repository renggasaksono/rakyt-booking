<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsAdmins;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-014 AC-5 / AC-8: a seller whose organization is suspended cannot sign in
 * to the seller app at all (no read-only mode), and a session that was
 * already open is rejected on its next request. Reactivation restores
 * sign-in. They see a neutral "akun ditangguhkan" message, never their data.
 */
class SuspendedSellerSignInTest extends TestCase
{
    use BuildsAdmins, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    private const NOTICE = 'Akun usaha ini sedang ditangguhkan, jadi belum bisa masuk. Untuk informasi lebih lanjut, hubungi tim Rakyt.';

    private Tenant $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->createTenant(['slug' => 'kencana', 'name' => 'Kencana Trip']);
        $this->seller->owner->forceFill(['email' => 'agus@example.com'])->save();
    }

    public function test_ac_5_valid_credentials_of_a_suspended_seller_sign_nobody_in(): void
    {
        $this->suspendOrganization($this->seller);
        $rememberToken = $this->seller->owner->remember_token;

        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'password', 'remember' => true])
            ->assertStatus(303)
            ->assertRedirect(route('login'))
            ->assertSessionHas('suspended', self::NOTICE)
            ->assertSessionHasNoErrors()
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());

        $this->assertGuest();
        // No session, no remember-me cookie, and not counted as activity (F-012).
        $this->assertNull($this->seller->owner->refresh()->last_login_at);
        $this->assertSame($rememberToken, $this->seller->owner->remember_token);

        $this->get(route('login'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('suspended', self::NOTICE)
        );
    }

    public function test_ac_5_a_wrong_password_still_gets_the_generic_message(): void
    {
        $this->suspendOrganization($this->seller);

        // Suspension is only revealed to someone who knows the password.
        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'salah-salah'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.'])
            ->assertSessionMissing('suspended');

        $this->assertGuest();
    }

    public function test_ac_5_an_open_session_is_rejected_on_its_next_request(): void
    {
        $this->actingAsTenant($this->seller)->get(route('dashboard'))->assertOk();

        $this->suspendOrganization($this->seller);

        $this->get(route('dashboard'))
            ->assertStatus(303)
            ->assertRedirect(route('login'))
            ->assertSessionHas('suspended', self::NOTICE);
        $this->assertGuest();

        // Signed out for good: the next visit is a guest's.
        $this->get(route('trips.index'))->assertRedirect(route('login'));
    }

    public function test_ac_5_every_seller_route_signs_a_suspended_seller_out_and_shows_nothing(): void
    {
        $this->suspendOrganization($this->seller);

        $requests = [
            ['GET', route('dashboard'), []],
            ['GET', route('bookings.index'), []],
            ['GET', route('trips.index'), []],
            ['GET', route('settings.index'), []],
            ['GET', route('settings.account'), []],
            ['GET', route('onboarding'), []],
            ['POST', route('trips.store'), ['name' => 'Trip baru']],
            ['PUT', route('settings.profile'), ['name' => 'Nama baru']],
            ['POST', route('logout'), []],
        ];

        foreach ($requests as [$method, $url, $data]) {
            $this->actingAsTenant($this->seller)
                ->call($method, $url, $data)
                ->assertRedirect(route('login'))
                ->assertDontSee('Kencana Trip');
            $this->assertGuest();
        }

        // A JSON caller (autosave, slug check) gets a 403 with the notice, no data.
        $this->actingAsTenant($this->seller)
            ->getJson(route('onboarding.slug', ['slug' => 'baru']))
            ->assertForbidden()
            ->assertExactJson(['message' => self::NOTICE]);
        $this->assertGuest();

        $this->assertDatabaseMissing('trips', ['name' => 'Trip baru']);
        $this->assertDatabaseHas('organizations', ['id' => $this->seller->id(), 'name' => 'Kencana Trip']);
    }

    public function test_ac_5_a_suspended_seller_cannot_reach_the_admin_actions_either(): void
    {
        $this->suspendOrganization($this->seller);

        $this->actingAsTenant($this->seller)
            ->post(route('admin.sellers.reactivate', $this->seller->id()), ['alasan' => 'Saya aktifkan sendiri'])
            ->assertRedirect(route('login'));

        $this->assertTrue($this->seller->organization->refresh()->isSuspended());
    }

    public function test_ac_5_a_remember_me_cookie_signs_nobody_in_and_stops_working(): void
    {
        $owner = $this->seller->owner;
        $owner->forceFill(['remember_token' => 'ingat-saya-token'])->save();
        $this->suspendOrganization($this->seller);

        $cookie = $owner->id.'|ingat-saya-token|'.$owner->getAuthPassword();

        $this->withCookie(Auth::guard('web')->getRecallerName(), $cookie)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($owner->refresh()->last_login_at);
        // logout() cycled the token: the old cookie never works again, even after reactivation.
        $this->assertNotSame('ingat-saya-token', $owner->remember_token);
    }

    public function test_ac_5_a_password_reset_saves_the_password_but_does_not_sign_in(): void
    {
        $this->suspendOrganization($this->seller);
        $token = Password::broker()->createToken($this->seller->owner);

        $this->post(route('password.store'), ['token' => $token, 'email' => 'agus@example.com', 'password' => 'baru12345'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('suspended', self::NOTICE);

        $this->assertGuest();
        $this->assertNull($this->seller->owner->refresh()->last_login_at);
        $this->assertTrue(Auth::guard('web')->validate(['email' => 'agus@example.com', 'password' => 'baru12345']));
    }

    public function test_ac_5_other_sellers_and_the_admin_are_not_affected(): void
    {
        $other = $this->createTenant(['slug' => 'bromo']);
        $admin = $this->platformAdmin();
        $this->suspendOrganization($this->seller);

        $this->actingAsTenant($other)->get(route('dashboard'))->assertOk();
        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.show', $this->seller->id()))->assertOk();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_ac_8_after_reactivation_the_seller_signs_in_normally(): void
    {
        $this->suspendOrganization($this->seller);
        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'password']);
        $this->assertGuest();

        $this->reactivateOrganization($this->seller);

        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->seller->owner);
        $this->get(route('dashboard'))->assertOk();
        $this->assertNotNull($this->seller->owner->refresh()->last_login_at);
    }

    public function test_ac_5_the_notice_is_neutral_on_the_sign_in_page(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Auth/Login.vue'));

        $this->assertStringContainsString('v-if="suspended"', $vue);
        // Neutral (Dibatalkan grey), not an error red or a success green.
        $this->assertMatchesRegularExpression('/v-if="suspended"[^>]*class="[^"]*bg-status-cancelled-tint/s', $vue);
    }
}
