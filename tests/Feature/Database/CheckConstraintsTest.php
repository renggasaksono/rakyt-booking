<?php

namespace Tests\Feature\Database;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F-009 A18b: CHECK constraints back up the application rules.
 */
class CheckConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ac_a18b_audit_actor_type_must_be_known(): void
    {
        $organization = Organization::factory()->create();

        $this->assertRejected(fn () => DB::table('audit_events')->insert([
            'organization_id' => $organization->id,
            'actor_type' => 'hacker',
            'action' => 'trip.published',
            'subject_type' => 'trip',
            'subject_id' => '1',
        ]), 'audit_events_actor_type_check');
    }

    public function test_ac_a18b_member_role_is_owner_only_in_p0(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->assertRejected(fn () => DB::table('organization_members')->insert([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]), 'organization_members_role_check');
    }

    private function assertRejected(callable $insert, string $constraint): void
    {
        try {
            DB::transaction($insert);
            $this->fail("Expected [{$constraint}] to reject the row.");
        } catch (QueryException $e) {
            $this->assertStringContainsString($constraint, $e->getMessage());
        }
    }
}
