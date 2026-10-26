<?php

namespace Tests\Feature\Admin;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditEventImmutable;
use App\Audit\AuditLog;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\BuildsAdmins;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-015: the admin audit view (`/admin/audit`) — every audit event across
 * organizations, filterable by organization, actor and action, read-only and
 * behind the F-010 gate.
 */
class AdminAuditLogTest extends TestCase
{
    use BuildsAdmins, InteractsWithTenants, RefreshDatabase;

    public function test_ac_5_the_audit_view_is_behind_the_admin_gate(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();

        $this->get(route('admin.audit.index'))->assertRedirect(route('login'));

        $this->actingAsTenant($seller)->get(route('admin.audit.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.audit.index'))
            ->assertRedirect(route('admin.two-factor.challenge'));

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Admin/AuditLog'));
    }

    public function test_ac_5_a_seller_cannot_read_admin_events_even_about_their_own_organization(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        $this->record(AuditAction::OrganizationSlugChanged, $seller->organization, Actor::platformAdmin($admin), reason: 'ALASAN-ADMIN');

        foreach ([
            route('admin.audit.index'),
            route('admin.audit.index', ['organisasi' => $seller->id()]),
            route('admin.audit.index', ['aktor' => 'platform_admin']),
        ] as $url) {
            $response = $this->actingAsTenant($seller)->get($url);
            $response->assertForbidden();
            $this->assertStringNotContainsString('ALASAN-ADMIN', (string) $response->getContent());
        }

        $this->assertTrue(Gate::forUser($seller->owner)->denies('viewAny', AuditEvent::class));
    }

    public function test_ac_2_each_event_shows_actor_action_target_timestamp_and_details(): void
    {
        $seller = $this->createTenant(['name' => 'Kencana Trip', 'slug' => 'kencana']);
        $admin = $this->platformAdmin(['name' => 'Rengga']);

        $this->travelTo('2026-10-26 07:05:00'); // 14.05 WIB
        $event = $this->record(
            AuditAction::OrganizationSlugChanged,
            $seller->organization,
            Actor::platformAdmin($admin),
            ['slug' => 'lama'],
            ['slug' => 'kencana'],
            'Permintaan penjual via WhatsApp',
        );

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $event->id)
                ->where('events.0.action', 'organization.slug_changed')
                ->where('events.0.action_label', __('admin.audit.actions.organization.slug_changed'))
                ->where('events.0.at', '2026-10-26T07:05:00Z')
                ->where('events.0.at_label', '26 Okt 2026 14.05 WIB')
                ->where('events.0.organization.id', $seller->id())
                ->where('events.0.organization.name', 'Kencana Trip')
                ->where('events.0.organization.slug', 'kencana')
                ->where('events.0.actor.type', 'platform_admin')
                ->where('events.0.actor.type_label', __('admin.audit.actor_types.platform_admin'))
                ->where('events.0.actor.id', (string) $admin->id)
                ->where('events.0.actor.name', 'Rengga')
                ->where('events.0.subject.type_label', __('admin.audit.subject_types.Organization'))
                ->where('events.0.subject.id', (string) $seller->id())
                ->where('events.0.reason', 'Permintaan penjual via WhatsApp')
                ->where('events.0.changes', [['field' => 'slug', 'before' => 'lama', 'after' => 'kencana']])
                ->where('events.0.ip', '127.0.0.1')
            );
    }

    public function test_ac_2_platform_level_events_show_without_an_organization(): void
    {
        $user = User::factory()->create(['name' => 'Calon Admin']);
        $this->artisan('admin:grant', ['email' => $user->email])->assertSuccessful();
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.action', 'platform_admin.granted')
                ->where('events.0.organization', null)
                ->where('events.0.actor.type', 'system')
                ->where('events.0.actor.id', 'admin:grant')
                ->where('events.0.subject.type_label', __('admin.audit.subject_types.User'))
                ->where('events.0.subject.id', (string) $user->id)
                ->where('events.0.subject.name', 'Calon Admin')
                ->where('events.0.changes', [['field' => 'is_platform_admin', 'before' => 'false', 'after' => 'true']])
            );
    }

    public function test_events_from_every_organization_are_listed_newest_first(): void
    {
        [$a, $b] = $this->createTenants();
        $admin = $this->platformAdmin();

        $first = $this->record(AuditAction::OrganizationSlugChanged, $a->organization);
        $this->travel(1)->minutes();
        $second = $this->record(AuditAction::OrganizationSlugChanged, $b->organization);

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'))
            ->assertInertia(fn ($page) => $page
                ->has('events', 2)
                ->where('events.0.id', $second->id)
                ->where('events.1.id', $first->id)
            );
    }

    public function test_events_can_be_filtered_by_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $admin = $this->platformAdmin();
        $ofA = $this->record(AuditAction::OrganizationSlugChanged, $a->organization);
        $this->record(AuditAction::OrganizationSlugChanged, $b->organization);
        $this->artisan('admin:grant', ['email' => User::factory()->create()->email]);
        $platform = AuditEvent::acrossOrganizations()->whereNull('organization_id')->sole();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index', ['organisasi' => $a->id()]))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $ofA->id)
                ->where('filters.organisasi', (string) $a->id())
            );

        // Platform-level events (no organization) have their own option.
        $this->get(route('admin.audit.index', ['organisasi' => 'platform']))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $platform->id)
                ->where('filters.organisasi', 'platform')
            );
    }

    public function test_events_can_be_filtered_by_actor_type_and_actor(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        $other = $this->platformAdmin();

        $byAdmin = $this->record(AuditAction::OrganizationSlugChanged, $seller->organization, Actor::platformAdmin($admin));
        $byOther = $this->record(AuditAction::OrganizationSlugChanged, $seller->organization, Actor::platformAdmin($other));
        $this->record(AuditAction::OrganizationSlugChanged, $seller->organization, Actor::user($seller->owner));

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index', ['aktor' => 'platform_admin']))
            ->assertInertia(fn ($page) => $page
                ->has('events', 2)
                ->where('filters.aktor', 'platform_admin')
            );

        $this->get(route('admin.audit.index', ['aktor' => 'platform_admin', 'aktor_id' => (string) $admin->id]))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $byAdmin->id)
                ->where('filters.aktor_id', (string) $admin->id)
            );

        // An actor id alone is ignored: ids are only unique within an actor type.
        $this->get(route('admin.audit.index', ['aktor_id' => (string) $other->id]))
            ->assertInertia(fn ($page) => $page->has('events', 3)->where('filters.aktor_id', null));

        $this->assertNotSame($byAdmin->id, $byOther->id);
    }

    public function test_events_can_be_filtered_by_action(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        $slug = $this->record(AuditAction::OrganizationSlugChanged, $seller->organization);
        $this->record(AuditAction::OrganizationCreated, $seller->organization);

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index', ['aksi' => 'organization.slug_changed']))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $slug->id)
                ->where('filters.aksi', 'organization.slug_changed')
            );
    }

    public function test_unknown_filter_values_are_ignored_and_the_list_still_renders(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        $this->record(AuditAction::OrganizationSlugChanged, $seller->organization);

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index', [
            'organisasi' => 'abc',
            'aktor' => 'hacker',
            'aktor_id' => str_repeat('9', 200),
            'aksi' => 'drop.table',
            'halaman' => '-3',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('filters', ['organisasi' => null, 'aktor' => null, 'aktor_id' => null, 'aksi' => null])
                ->where('pagination.page', 1)
            );
    }

    public function test_filter_options_list_organizations_actor_types_and_every_action(): void
    {
        $this->createTenant(['name' => 'Bromo Adventure', 'slug' => 'bromo']);
        $this->createTenant(['name' => 'Arjuna Trip', 'slug' => 'arjuna']);
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'))
            ->assertInertia(fn ($page) => $page
                ->where('options.organizations.0.name', 'Arjuna Trip')
                ->where('options.organizations.1.name', 'Bromo Adventure')
                ->has('options.actor_types', 5)
                ->has('options.actions', count(AuditAction::cases()))
            );
    }

    public function test_every_action_actor_type_and_subject_type_has_a_label(): void
    {
        foreach (AuditAction::cases() as $action) {
            $this->assertNotSame('admin.audit.actions.'.$action->value, __('admin.audit.actions.'.$action->value), $action->value);
        }

        foreach (['user', 'customer', 'system', 'rakyt_staff', 'platform_admin'] as $type) {
            $this->assertNotSame('admin.audit.actor_types.'.$type, __('admin.audit.actor_types.'.$type), $type);
        }
    }

    public function test_the_list_is_paginated(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        $perPage = AdminAuditController::PER_PAGE;

        for ($i = 0; $i <= $perPage; $i++) {
            $this->record(AuditAction::OrganizationSlugChanged, $seller->organization);
        }

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index', ['aksi' => 'organization.slug_changed']))
            ->assertInertia(fn ($page) => $page
                ->has('events', $perPage)
                ->where('pagination.prev_url', null)
                ->where('pagination.next_url', route('admin.audit.index', ['aksi' => 'organization.slug_changed', 'halaman' => 2]))
            );

        $this->get(route('admin.audit.index', ['aksi' => 'organization.slug_changed', 'halaman' => 2]))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('pagination.page', 2)
                ->where('pagination.next_url', null)
                ->where('pagination.prev_url', route('admin.audit.index', ['aksi' => 'organization.slug_changed']))
            );
    }

    public function test_ac_3_the_view_never_shows_secrets_even_from_a_raw_row(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();

        // A row written around AuditLog (e.g. by a future bug): the view redacts it too.
        DB::table('audit_events')->insert([
            'organization_id' => $seller->id(),
            'actor_type' => 'platform_admin',
            'actor_id' => (string) $admin->id,
            'action' => 'organization.slug_changed',
            'subject_type' => (new Organization)->getMorphClass(),
            'subject_id' => (string) $seller->id(),
            'before' => json_encode(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'slug' => 'lama']),
            'after' => json_encode(['password' => 'hash', 'otp' => '123456', 'remember_token' => 'tok', 'customer_phone' => '+6281234', 'slug' => 'baru']),
            'created_at' => now()->utc(),
        ]);

        $response = $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('events.0.changes', [['field' => 'slug', 'before' => 'lama', 'after' => 'baru']])
        );

        foreach (['JBSWY3DPEHPK3PXP', '123456', '+6281234', 'remember_token', 'two_factor_secret'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $response->getContent());
        }
    }

    public function test_ac_4_audit_events_have_no_write_routes(): void
    {
        $seller = $this->createTenant();
        $admin = $this->platformAdmin();
        $event = $this->record(AuditAction::OrganizationSlugChanged, $seller->organization, Actor::platformAdmin($admin));

        // Only reads are routed: every route touching the audit log is a GET.
        $auditRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit') || str_contains((string) $route->getName(), 'audit'));
        $this->assertNotEmpty($auditRoutes);
        foreach ($auditRoutes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri().' must be read-only.');
        }

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            foreach (['/admin/audit', '/admin/audit/'.$event->id] as $uri) {
                $status = $this->actingAsConfirmedAdmin($admin)->call($method, $uri, ['reason' => 'diubah'])->getStatusCode();
                $this->assertContains($status, [404, 405], "{$method} {$uri} must not exist.");
            }
        }

        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'reason' => null]);
    }

    public function test_ac_4_admin_events_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $this->artisan('admin:grant', ['email' => User::factory()->create()->email]);
        $event = AuditEvent::acrossOrganizations()->sole();

        foreach ([fn () => $event->update(['reason' => 'x']), fn () => $event->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('An audit event was changed.');
            } catch (AuditEventImmutable) {
                // expected
            }
        }

        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_the_admin_nav_links_to_the_audit_log(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.audit.index'))
            ->assertInertia(fn ($page) => $page
                ->where('admin.nav.1.key', 'audit')
                ->where('admin.nav.1.label', __('admin.nav.audit'))
                ->where('admin.nav.1.url', route('admin.audit.index'))
                ->where('admin.nav.1.active', true)
                ->where('admin.nav.0.active', false)
            );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function record(
        AuditAction $action,
        Model $subject,
        ?Actor $actor = null,
        array $before = ['slug' => 'lama'],
        array $after = ['slug' => 'baru'],
        ?string $reason = null,
    ): AuditEvent {
        app(CurrentOrganization::class)->set(null);

        return DB::transaction(fn () => app(AuditLog::class)->record(
            $action,
            $subject,
            $before,
            $after,
            $actor ?? Actor::system('test'),
            $reason,
        ));
    }
}
