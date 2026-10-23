<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsAdmins;
use Tests\TestCase;

/**
 * F-010 AC: the platform-admin flag is set only by the artisan command/seeder,
 * never over HTTP, and granting/revoking it is audited.
 */
class PlatformAdminFlagTest extends TestCase
{
    use BuildsAdmins, RefreshDatabase;

    public function test_ac_the_flag_and_totp_secret_are_not_mass_assignable(): void
    {
        $user = new User;

        $this->assertFalse($user->isFillable('is_platform_admin'));
        $this->assertFalse($user->isFillable('two_factor_secret'));
    }

    public function test_ac_the_flag_cannot_be_set_through_an_http_request(): void
    {
        // A sign-up payload that smuggles the flag must not create an admin:
        // the field is not fillable, so it never reaches the created row.
        $this->post(route('register'), [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'secret-password',
            'terms' => true,
            'is_platform_admin' => true,
            'two_factor_secret' => 'injected',
        ]);

        $user = User::query()->where('email', 'budi@example.com')->first();

        $this->assertNotNull($user, 'The sign-up should still create the account.');
        $this->assertFalse($user->is_platform_admin);
        $this->assertNull($user->two_factor_secret);
    }

    public function test_ac_the_secret_is_hidden_from_serialization(): void
    {
        $admin = $this->platformAdmin();

        $this->assertArrayNotHasKey('two_factor_secret', $admin->toArray());
    }

    public function test_ac_grant_command_sets_the_flag_enrols_totp_and_audits_it(): void
    {
        $user = User::factory()->create();

        $this->artisan('admin:grant', ['email' => $user->email])
            ->expectsOutputToContain('Granted platform-admin access')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->is_platform_admin);
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);

        $event = AuditEvent::acrossOrganizations()->sole();
        $this->assertNull($event->organization_id, 'A platform-level event belongs to no organization.');
        $this->assertSame('system', $event->actor_type);
        $this->assertSame('admin:grant', $event->actor_id);
        $this->assertSame('platform_admin.granted', $event->action);
        $this->assertSame($user->getMorphClass(), $event->subject_type);
        $this->assertSame((string) $user->getKey(), $event->subject_id);
        $this->assertSame(['is_platform_admin' => false], $event->before);
        $this->assertSame(['is_platform_admin' => true], $event->after);
    }

    public function test_ac_grant_command_never_logs_the_secret(): void
    {
        $user = User::factory()->create();

        $this->artisan('admin:grant', ['email' => $user->email])->assertSuccessful();

        $event = AuditEvent::acrossOrganizations()->sole();
        $this->assertArrayNotHasKey('two_factor_secret', $event->after ?? []);
        $this->assertArrayNotHasKey('two_factor_secret', $event->before ?? []);
    }

    public function test_ac_revoke_command_clears_the_flag_and_audits_it(): void
    {
        $admin = $this->platformAdmin();

        $this->artisan('admin:revoke', ['email' => $admin->email])
            ->expectsOutputToContain('Revoked platform-admin access')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertFalse($admin->is_platform_admin);
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);

        $revoked = AuditEvent::acrossOrganizations()->where('action', 'platform_admin.revoked')->sole();
        $this->assertNull($revoked->organization_id);
        $this->assertSame('system', $revoked->actor_type);
        $this->assertSame('admin:revoke', $revoked->actor_id);
        $this->assertSame(['is_platform_admin' => true], $revoked->before);
        $this->assertSame(['is_platform_admin' => false], $revoked->after);
    }

    public function test_ac_grant_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('admin:grant', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);

        $this->assertDatabaseCount('audit_events', 0);
    }
}
