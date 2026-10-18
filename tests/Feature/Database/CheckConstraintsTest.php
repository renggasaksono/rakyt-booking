<?php

namespace Tests\Feature\Database;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_f003_organization_time_zone_must_be_indonesian(): void
    {
        $this->assertRejected(fn () => DB::table('organizations')->insert([
            'public_id' => (string) Str::uuid(),
            'timezone' => 'Europe/Amsterdam',
        ]), 'organizations_timezone_check');
    }

    public function test_f003_seats_taken_can_never_exceed_capacity(): void
    {
        $organization = Organization::factory()->create();
        $tripId = DB::table('trips')->insertGetId([
            'organization_id' => $organization->id,
            'public_id' => strtolower((string) Str::ulid()),
            'slug' => 'karimunjawa',
            'type' => 'open_trip',
            'booking_mode' => 'scheduled',
            'name' => 'Karimunjawa',
        ]);

        $this->assertRejected(fn () => DB::table('trip_departures')->insert([
            'organization_id' => $organization->id,
            'trip_id' => $tripId,
            'public_id' => strtolower((string) Str::ulid()),
            'start_date' => '2030-01-10',
            'end_date' => '2030-01-12',
            'capacity' => 16,
            'seats_taken' => 17,
            'booking_deadline_preset' => 'h3',
            'booking_deadline_at' => '2030-01-07 16:59:59',
        ]), 'trip_departures_seats_taken_check');
    }

    public function test_f003_a_departure_cannot_point_at_another_organizations_trip(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        $tripId = DB::table('trips')->insertGetId([
            'organization_id' => $a->id,
            'public_id' => strtolower((string) Str::ulid()),
            'slug' => 'karimunjawa',
            'type' => 'open_trip',
            'booking_mode' => 'scheduled',
            'name' => 'Karimunjawa',
        ]);

        $this->assertRejected(fn () => DB::table('trip_departures')->insert([
            'organization_id' => $b->id,
            'trip_id' => $tripId,
            'public_id' => strtolower((string) Str::ulid()),
            'start_date' => '2030-01-10',
            'end_date' => '2030-01-12',
            'capacity' => 16,
            'booking_deadline_preset' => 'h3',
            'booking_deadline_at' => '2030-01-07 16:59:59',
        ]), 'trip_departures_organization_id_trip_id_foreign');
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
