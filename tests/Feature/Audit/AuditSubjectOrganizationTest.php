<?php

namespace Tests\Feature\Audit;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\AuditEvent;
use App\Models\Trip;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 R4 (done with F-001): a User subject is audited against their
 * organization; a subject from another organization than the current one throws.
 */
class AuditSubjectOrganizationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_r4_a_guest_action_on_a_user_is_audited_against_their_membership(): void
    {
        $tenant = $this->createTenant();
        $this->assertFalse(app(CurrentOrganization::class)->check());

        DB::transaction(fn () => app(AuditLog::class)->record(AuditAction::AuthPasswordReset, $tenant->owner, actor: Actor::user($tenant->owner)));

        $this->assertSame($tenant->id(), AuditEvent::acrossOrganizations()->sole()->organization_id);
    }

    public function test_r4_a_user_without_an_organization_cannot_be_audited(): void
    {
        $user = User::factory()->create();

        $this->expectException(LogicException::class);
        DB::transaction(fn () => app(AuditLog::class)->record(AuditAction::AuthPasswordReset, $user, actor: Actor::user($user)));
    }

    public function test_r4_a_subject_of_another_organization_throws(): void
    {
        [$a, $b] = $this->createTenants();
        $trip = $this->asTenant($a, fn () => Trip::factory()->create());

        $this->expectException(LogicException::class);
        $this->asTenant($b, fn () => DB::transaction(
            fn () => app(AuditLog::class)->record(AuditAction::TripPublished, $trip, actor: Actor::user($b->owner)),
        ));
    }
}
