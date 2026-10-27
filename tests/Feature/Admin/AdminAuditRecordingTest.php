<?php

namespace Tests\Feature\Admin;

use App\Admin\Queries\AuditEventQueries;
use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\AuditEvent;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use RuntimeException;
use Tests\Support\BuildsAdmins;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-015: how admin actions are recorded in F-009's `audit_events` (no second
 * log), and the admin query helpers F-012 / F-013 reuse for related events.
 */
class AdminAuditRecordingTest extends TestCase
{
    use BuildsAdmins, InteractsWithTenants, RefreshDatabase;

    /**
     * Every admin route that changes something, and the audit action it must
     * write (F-015: "any sensitive admin change added later must be audited
     * too"). Add a new write route here together with its audit test; `null`
     * marks a route that changes no platform or seller data.
     */
    private const ADMIN_WRITE_ROUTES = [
        // The TOTP challenge only confirms the admin's own session (F-010).
        'admin.two-factor.verify' => null,
    ];

    /**
     * Why each `null` route above writes no audit event. Anything but the TOTP
     * challenge needs a justification here, or the route must audit.
     */
    private const UNAUDITED_ROUTES = [
        'admin.two-factor.verify' => 'Confirms the admin\'s own session; changes no platform or seller data (F-010).',
    ];

    public function test_ac_1_granting_and_revoking_admin_access_each_write_exactly_one_event(): void
    {
        $user = User::factory()->create();

        $this->artisan('admin:grant', ['email' => $user->email])->assertSuccessful();
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'platform_admin.granted')->count());
        $this->assertDatabaseCount('audit_events', 1);

        $this->artisan('admin:revoke', ['email' => $user->email])->assertSuccessful();
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'platform_admin.revoked')->count());
        $this->assertDatabaseCount('audit_events', 2);

        // Revoking a non-admin changes nothing and writes nothing.
        $this->artisan('admin:revoke', ['email' => $user->email])->assertSuccessful();
        $this->assertDatabaseCount('audit_events', 2);
    }

    public function test_ac_1_a_failed_audit_write_rolls_back_the_admin_change(): void
    {
        $user = User::factory()->create();
        AuditEvent::creating(fn () => throw new RuntimeException('audit store down'));

        try {
            $this->artisan('admin:grant', ['email' => $user->email])->run();
            $this->fail('The grant should fail when its audit event cannot be written.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store down', $e->getMessage());
        }

        $user->refresh();
        $this->assertFalse($user->is_platform_admin, 'The grant must not survive without its audit event.');
        $this->assertNull($user->two_factor_secret);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_ac_1_a_failed_audit_write_rolls_back_the_revoke(): void
    {
        $user = User::factory()->create();
        $this->artisan('admin:grant', ['email' => $user->email])->assertSuccessful();
        $secret = $user->refresh()->two_factor_secret;
        AuditEvent::creating(fn () => throw new RuntimeException('audit store down'));

        try {
            $this->artisan('admin:revoke', ['email' => $user->email])->run();
            $this->fail('The revoke should fail when its audit event cannot be written.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store down', $e->getMessage());
        }

        $user->refresh();
        $this->assertTrue($user->is_platform_admin, 'The revoke must not survive without its audit event.');
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_ac_2_an_admin_action_records_the_admin_the_target_and_the_reason(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        app(CurrentOrganization::class)->set(null); // an admin request has no current organization

        $event = DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $seller->organization,
            ['slug' => 'lama'],
            ['slug' => 'baru'],
            Actor::platformAdmin($admin),
            reason: '  Permintaan penjual  ',
        ));

        $event = AuditEvent::acrossOrganizations()->findOrFail($event->id);
        $this->assertSame('platform_admin', $event->actor_type);
        $this->assertSame((string) $admin->id, $event->actor_id);
        $this->assertSame('organization.slug_changed', $event->action);
        $this->assertSame($seller->organization->getMorphClass(), $event->subject_type);
        $this->assertSame((string) $seller->id(), $event->subject_id);
        $this->assertSame($seller->id(), $event->organization_id);
        $this->assertSame('Permintaan penjual', $event->reason);
        $this->assertSame(['slug' => 'lama'], $event->before);
        $this->assertSame(['slug' => 'baru'], $event->after);
        $this->assertNotNull($event->created_at);
        $this->assertSame('127.0.0.1', $event->ip);
    }

    public function test_ac_2_a_blank_reason_is_stored_as_none(): void
    {
        $seller = $this->createTenant();
        app(CurrentOrganization::class)->set(null);

        $event = DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $seller->organization,
            actor: Actor::system('test'),
            reason: '   ',
        ));

        $this->assertNull(AuditEvent::acrossOrganizations()->findOrFail($event->id)->reason);
    }

    public function test_ac_3_secrets_otp_codes_evidence_and_customer_details_are_never_stored(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        app(CurrentOrganization::class)->set(null);

        $event = DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $seller->organization,
            [
                'password' => 'old-hash',
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
                'two_factor_recovery_codes' => ['a', 'b'],
                'slug' => 'lama',
            ],
            [
                'otp' => '123456',
                'code' => '654321',
                'access_token' => 'tok',
                'evidence_path' => 'payments/evidence/1.jpg',
                'customer_name' => 'Siti',
                'customer_phone' => '+6281234567890',
                'notes' => 'alergi kacang',
                'booking_id' => 42,
                'nested' => ['remember_token' => 'x', 'kept' => 'ya'],
                'slug' => 'baru',
            ],
            Actor::platformAdmin($admin),
        ));

        $event = AuditEvent::acrossOrganizations()->findOrFail($event->id);
        $this->assertSame(['slug' => 'lama'], $event->before);
        // jsonb does not keep key order.
        $this->assertEquals(['booking_id' => 42, 'nested' => ['kept' => 'ya'], 'slug' => 'baru'], $event->after);
    }

    public function test_ac_3_the_grant_event_never_holds_the_totp_secret(): void
    {
        $user = User::factory()->create();
        $this->artisan('admin:grant', ['email' => $user->email])->assertSuccessful();

        $secret = $user->refresh()->two_factor_secret;
        $row = (array) DB::table('audit_events')->sole();

        $this->assertStringNotContainsString($secret, json_encode($row));
    }

    public function test_every_admin_write_route_is_reviewed_for_auditing(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'admin.'))
            ->reject(fn (Route $route) => $route->methods() === ['GET', 'HEAD'])
            ->map(fn (Route $route) => $route->getName())
            ->sort()->values()->all();

        $this->assertSame(
            collect(array_keys(self::ADMIN_WRITE_ROUTES))->sort()->values()->all(),
            $writes,
            'A new admin write route must be listed in ADMIN_WRITE_ROUTES with the audit action it writes (F-015).',
        );

        foreach (self::ADMIN_WRITE_ROUTES as $route => $action) {
            if ($action === null) {
                $this->assertNotEmpty(
                    self::UNAUDITED_ROUTES[$route] ?? null,
                    "{$route} writes no audit event: audit it, or justify it in UNAUDITED_ROUTES (F-015).",
                );

                continue;
            }

            $this->assertNotNull(AuditAction::tryFrom($action), "{$route} names an unknown audit action {$action}.");
        }

        $this->assertSame([], array_values(array_diff(
            array_keys(self::UNAUDITED_ROUTES),
            array_keys(array_filter(self::ADMIN_WRITE_ROUTES, fn (?string $action) => $action === null)),
        )), 'UNAUDITED_ROUTES may only justify routes listed as null in ADMIN_WRITE_ROUTES.');
    }

    public function test_related_events_for_an_organization_are_read_across_organizations(): void
    {
        [$a, $b] = $this->createTenants();
        $first = $this->recordFor($a);
        $this->travel(1)->minutes();
        $second = $this->recordFor($a);
        $this->recordFor($b);

        // No current organization (an admin request): the query still finds them.
        app(CurrentOrganization::class)->set(null);
        $events = app(AuditEventQueries::class)->forOrganization($a->organization);

        $this->assertSame([$second->id, $first->id], $events->pluck('id')->all());
        $this->assertCount(1, app(AuditEventQueries::class)->forOrganization($a->organization, limit: 1));
    }

    public function test_related_events_for_a_subject_are_read_across_organizations(): void
    {
        [$a, $b] = $this->createTenants();
        $ofA = $this->recordFor($a);
        $this->recordFor($b);

        app(CurrentOrganization::class)->set(null);
        $events = app(AuditEventQueries::class)->forSubject($a->organization);

        $this->assertSame([$ofA->id], $events->pluck('id')->all());
    }

    public function test_related_events_for_a_seller_owned_subject_are_matched_on_its_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $ofA = $this->recordFor($a);
        // A row under another organization with the same subject (written
        // around AuditLog) is not an event about $a's organization.
        DB::table('audit_events')->insert([
            'organization_id' => $b->id(),
            'actor_type' => 'system',
            'action' => 'organization.slug_changed',
            'subject_type' => $a->organization->getMorphClass(),
            'subject_id' => (string) $a->id(),
        ]);

        app(CurrentOrganization::class)->set(null);

        $this->assertSame([$ofA->id], app(AuditEventQueries::class)->forSubject($a->organization)->pluck('id')->all());
    }

    public function test_related_events_for_a_user_include_platform_level_events(): void
    {
        $user = User::factory()->create();
        $this->artisan('admin:grant', ['email' => $user->email])->assertSuccessful();
        $this->artisan('admin:revoke', ['email' => $user->email])->assertSuccessful();

        $events = app(AuditEventQueries::class)->forSubject($user);

        $this->assertSame(['platform_admin.revoked', 'platform_admin.granted'], $events->pluck('action')->all());
    }

    private function recordFor(Tenant $tenant): AuditEvent
    {
        app(CurrentOrganization::class)->set(null);

        return DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $tenant->organization,
            ['slug' => 'lama'],
            ['slug' => 'baru'],
            Actor::system('test'),
        ));
    }
}
