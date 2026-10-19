<?php

namespace Tests\Feature\Bookings;

use App\Models\Organization;
use App\Organizations\BookingCode;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-005 AC-17b: every organization has a booking code, derived from its slug
 * once, unique, never RKB (ADR-0026 §5).
 */
class BookingCodeTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_17b_the_code_is_derived_from_the_slug(): void
    {
        $this->assertSame('BRM', Organization::factory()->create(['slug' => 'bromo-adventure'])->booking_code);
        $this->assertSame('KMD', Organization::factory()->create(['slug' => 'komodo-trip'])->booking_code);
        $this->assertSame('KNC', Organization::factory()->create(['slug' => 'kencana'])->booking_code);
        $this->assertSame('ABX', Organization::factory()->create(['slug' => 'ab1'])->booking_code);
    }

    public function test_ac_17b_onboarding_sign_up_gives_the_organization_a_code(): void
    {
        $this->actingAs($this->signedUpUser())
            ->post(route('onboarding.organization'), $this->organizationInput(['slug' => 'bromo-adventure']))
            ->assertRedirect();

        $this->assertSame('BRM', Organization::query()->where('slug', 'bromo-adventure')->value('booking_code'));
    }

    public function test_ac_17b_a_taken_code_moves_to_the_next_candidate(): void
    {
        Organization::factory()->create(['slug' => 'bromo-adventure']);

        $this->assertSame('BRD', Organization::factory()->create(['slug' => 'bromo-adventures'])->booking_code);
        $this->assertSame('BRV', Organization::factory()->create(['slug' => 'bromo-adventure-2'])->booking_code);
    }

    public function test_ac_17b_the_unique_index_decides_a_race_and_the_loser_gets_the_next_candidate(): void
    {
        // Another seller's insert takes BRM after this one picked it.
        $calls = 0;
        Organization::creating(function () use (&$calls) {
            if ($calls++ === 0) {
                DB::table('organizations')->insert(['public_id' => (string) str()->uuid(), 'name' => 'Lain', 'slug' => 'bromo-lain', 'city' => 'Malang', 'whatsapp' => '+6281200000000', 'booking_code' => 'BRM']);
            }
        });

        $organization = Organization::factory()->create(['slug' => 'bromo-adventure']);

        $this->assertSame('BRD', $organization->booking_code);
        $this->assertSame(2, $calls, 'The insert was retried once.');
    }

    public function test_ac_17b_the_code_is_never_rkb(): void
    {
        $this->assertFalse(BookingCode::isAllowed('RKB'));
        $this->assertSame('RKT', Organization::factory()->create(['slug' => 'rkb-tours'])->booking_code);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('organizations_booking_code_check');
        DB::table('organizations')->insert(['public_id' => (string) str()->uuid(), 'name' => 'X', 'slug' => 'x-rkb', 'city' => 'X', 'whatsapp' => '+6281200000000', 'booking_code' => 'RKB']);
    }

    public function test_ac_17b_the_code_survives_a_slug_or_name_change_and_cannot_be_edited(): void
    {
        $organization = Organization::factory()->create(['slug' => 'bromo-adventure', 'name' => 'Bromo Adventure']);

        $organization->update(['slug' => 'komodo-trip', 'name' => 'Komodo Trip']);
        $this->assertSame('BRM', $organization->fresh()->booking_code);

        $this->assertContains('booking_code', $organization->getGuarded(), 'Not mass-assignable.');

        $this->expectException(LogicException::class);
        $organization->forceFill(['booking_code' => 'KMD'])->save();
    }

    public function test_ac_17b_the_migration_backfills_existing_organizations(): void
    {
        $migration = require database_path('migrations/2026_10_19_000004_add_booking_code_to_organizations.php');
        $migration->down();

        foreach (['bromo-adventure', 'bromo-adventures', 'komodo-trip'] as $slug) {
            DB::table('organizations')->insert(['public_id' => (string) str()->uuid(), 'name' => $slug, 'slug' => $slug, 'city' => 'X', 'whatsapp' => '+6281200000000']);
        }

        $migration->up();

        $this->assertSame(
            ['bromo-adventure' => 'BRM', 'bromo-adventures' => 'BRD', 'komodo-trip' => 'KMD'],
            DB::table('organizations')->whereIn('slug', ['bromo-adventure', 'bromo-adventures', 'komodo-trip'])->orderBy('id')->pluck('booking_code', 'slug')->all(),
        );
        $this->assertSame(0, DB::table('organizations')->whereNull('booking_code')->count());
    }
}
