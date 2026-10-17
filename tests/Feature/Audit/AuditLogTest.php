<?php

namespace Tests\Feature\Audit;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditEventImmutable;
use App\Audit\AuditLog;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Tenancy\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\Fixtures\Tenancy\ProbeNote;
use Tests\Fixtures\Tenancy\RegistersProbeNotes;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 A10–A13.
 */
class AuditLogTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RegistersProbeNotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerProbeNotes();
    }

    public function test_ac_a10_change_and_audit_event_are_written_in_one_transaction(): void
    {
        [$a] = $this->createTenants();
        $this->actingAs($a->owner);
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Lama']));

        $this->asTenant($a, fn () => DB::transaction(function () use ($note) {
            $note->update(['title' => 'Baru']);
            app(AuditLog::class)->recordChanges(AuditAction::DepartureCapacityChanged, $note);
        }));

        $event = AuditEvent::acrossOrganizations()->sole();
        $this->assertSame($a->id(), $event->organization_id);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame((string) $a->owner->id, $event->actor_id);
        $this->assertSame('departure.capacity_changed', $event->action);
        $this->assertSame($note->getMorphClass(), $event->subject_type);
        $this->assertSame((string) $note->id, $event->subject_id);
        $this->assertSame(['title' => 'Lama'], $event->before);
        $this->assertSame(['title' => 'Baru'], $event->after);
        $this->assertSame('UTC', $event->created_at->getTimezone()->getName());
    }

    public function test_ac_a10_rolled_back_change_leaves_no_audit_event(): void
    {
        [$a] = $this->createTenants();
        $this->actingAs($a->owner);
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Lama']));

        try {
            $this->asTenant($a, fn () => DB::transaction(function () use ($note) {
                $note->update(['title' => 'Baru']);
                app(AuditLog::class)->recordChanges(AuditAction::DepartureCapacityChanged, $note);

                throw new RuntimeException('simulated failure');
            }));
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseHas('probe_notes', ['id' => $note->id, 'title' => 'Lama']);
    }

    public function test_ac_a11_system_changes_are_attributed_to_the_named_job(): void
    {
        [$a] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'x']));
        app(CurrentOrganization::class)->set(null);

        $event = DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::BookingHoldExpired,
            $note,
            ['status' => 'held'],
            ['status' => 'expired'],
            Actor::system('ExpireSeatHolds'),
        ));

        $this->assertSame('system', $event->actor_type);
        $this->assertSame('ExpireSeatHolds', $event->actor_id);
        $this->assertSame($a->id(), $event->organization_id);
        $this->assertNull($event->ip);
    }

    public function test_ac_a10_secrets_are_never_stored_in_before_or_after(): void
    {
        [$a] = $this->createTenants();
        $this->actingAs($a->owner);

        $event = $this->asTenant($a, fn () => DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::AuthPasswordChanged,
            $a->owner,
            ['password' => 'old-hash', 'remember_token' => 'abc'],
            ['password' => 'new-hash', 'email' => 'budi@contoh.id'],
        )));

        $this->assertNull($event->before);
        $this->assertSame(['email' => 'budi@contoh.id'], $event->after);
    }

    public function test_organization_subjects_are_audited_under_their_own_organization(): void
    {
        [$a] = $this->createTenants();
        app(CurrentOrganization::class)->set(null);

        $event = DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $a->organization,
            ['slug' => 'lama'],
            ['slug' => 'baru'],
            Actor::rakytStaff('rengga'),
        ));

        $this->assertSame($a->id(), $event->organization_id);
        $this->assertSame('rakyt_staff', $event->actor_type);
    }

    public function test_ac_a12_audit_events_cannot_be_updated_or_deleted_by_the_app(): void
    {
        [$a] = $this->createTenants();
        $event = $this->recordEventFor($a->organization);

        $this->asTenant($a, function () use ($event) {
            try {
                $event->update(['action' => 'trip.published']);
                $this->fail('Update should throw.');
            } catch (AuditEventImmutable) {
            }

            try {
                $event->delete();
                $this->fail('Delete should throw.');
            } catch (AuditEventImmutable) {
            }
        });

        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'action' => 'organization.slug_changed']);
    }

    public function test_ac_a12_database_rejects_updates_and_deletes_that_bypass_the_model(): void
    {
        [$a] = $this->createTenants();
        $event = $this->recordEventFor($a->organization);

        foreach ([
            fn () => DB::table('audit_events')->where('id', $event->id)->update(['action' => 'x']),
            fn () => DB::table('audit_events')->where('id', $event->id)->delete(),
        ] as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('The database should reject the change.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }
    }

    public function test_ac_a12_deleting_the_subject_keeps_its_audit_events(): void
    {
        [$a] = $this->createTenants();
        $this->actingAs($a->owner);
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'x']));

        $this->asTenant($a, fn () => DB::transaction(function () use ($note) {
            app(AuditLog::class)->record(AuditAction::DepartureDeleted, $note, ['title' => 'x']);
            $note->delete();
        }));

        $this->assertDatabaseHas('audit_events', ['subject_id' => (string) $note->id, 'action' => 'departure.deleted']);
    }

    public function test_ac_a13_audit_events_are_tenant_scoped_and_hidden_from_sellers(): void
    {
        [$a, $b] = $this->createTenants();
        $this->recordEventFor($a->organization);

        $this->assertSame(0, $this->asTenant($b, fn () => AuditEvent::query()->count()));
        $this->assertSame(1, $this->asTenant($a, fn () => AuditEvent::query()->count()));
        $this->assertTrue(Gate::forUser($a->owner)->denies('viewAny', AuditEvent::class));
    }

    private function recordEventFor(Organization $organization): AuditEvent
    {
        app(CurrentOrganization::class)->set(null);

        return DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $organization,
            ['slug' => 'lama'],
            ['slug' => 'baru'],
            Actor::system('test'),
        ));
    }
}
