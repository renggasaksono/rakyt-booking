<?php

namespace Tests\Feature\Tenancy;

use App\Models\OrganizationMember;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Exceptions\BulkWriteBypassesTenancy;
use App\Tenancy\Exceptions\MissingOrganizationContext;
use App\Tenancy\Exceptions\OrganizationIdImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Tenancy\ProbeNote;
use Tests\Fixtures\Tenancy\ProbeTrashableNote;
use Tests\Fixtures\Tenancy\RegistersProbeNotes;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 A1, A4 and the fail-closed default.
 */
class OrganizationScopingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RegistersProbeNotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerProbeNotes();
    }

    public function test_ac_a1_index_count_and_search_only_return_own_rows(): void
    {
        [$a, $b] = $this->createTenants();
        $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Trip Komodo A']));
        $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Trip Komodo B']));

        $this->actingAsTenant($a)->getJson('/probe-notes')
            ->assertOk()
            ->assertExactJson(['titles' => ['Trip Komodo A'], 'count' => 1]);

        $this->actingAsTenant($a)->getJson('/probe-notes?q=Komodo')
            ->assertExactJson(['titles' => ['Trip Komodo A'], 'count' => 1]);
    }

    public function test_ac_a1_seller_with_no_records_sees_an_empty_list(): void
    {
        [$a, $b] = $this->createTenants();
        $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Milik B']));

        $this->actingAsTenant($a)->getJson('/probe-notes')
            ->assertExactJson(['titles' => [], 'count' => 0]);
    }

    public function test_without_a_current_organization_scoped_queries_return_nothing(): void
    {
        [$a] = $this->createTenants();
        $this->asTenant($a, fn () => ProbeNote::create(['title' => 'A']));

        app(CurrentOrganization::class)->set(null);

        $this->assertSame(0, ProbeNote::query()->count());
        $this->assertSame(1, ProbeNote::acrossOrganizations()->count());
    }

    public function test_ac_a4_organization_id_is_filled_from_the_current_organization(): void
    {
        [$a] = $this->createTenants();

        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Baru']));

        $this->assertSame($a->id(), $note->organization_id);
    }

    public function test_ac_a4_organization_id_cannot_be_mass_assigned(): void
    {
        [$a, $b] = $this->createTenants();

        $this->expectException(MassAssignmentException::class);

        $this->asTenant($a, fn () => ProbeNote::create(['title' => 'x', 'organization_id' => $b->id()]));
    }

    public function test_ac_a4_a_forced_organization_id_is_overridden_by_the_current_organization(): void
    {
        [$a, $b] = $this->createTenants();

        $note = $this->asTenant($a, function () use ($b) {
            $note = new ProbeNote(['title' => 'x']);
            $note->organization_id = $b->id();
            $note->save();

            return $note;
        });

        $this->assertSame($a->id(), $note->organization_id);
    }

    public function test_ac_a4_a_request_that_sends_organization_id_never_writes_to_another_organization(): void
    {
        [$a, $b] = $this->createTenants();

        $this->actingAsTenant($a)->postJson('/probe-notes', ['title' => 'x', 'organization_id' => $b->id()]);

        $this->assertDatabaseMissing('probe_notes', ['organization_id' => $b->id()]);
    }

    public function test_ac_a4_organization_id_cannot_change_after_create(): void
    {
        [$a, $b] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'x']));

        try {
            $this->asTenant($a, function () use ($note, $b) {
                $note->organization_id = $b->id();
                $note->save();
            });
            $this->fail('Changing organization_id should throw.');
        } catch (OrganizationIdImmutable) {
            $this->assertDatabaseHas('probe_notes', ['id' => $note->id, 'organization_id' => $a->id()]);
        }
    }

    public function test_creating_without_any_organization_context_throws(): void
    {
        app(CurrentOrganization::class)->set(null);

        $this->expectException(MissingOrganizationContext::class);

        ProbeNote::create(['title' => 'tanpa organisasi']);
    }

    public function test_ac_a4_bulk_inserts_cannot_write_into_another_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $row = fn (string $title) => ['title' => $title, 'organization_id' => $b->id(), 'created_at' => now(), 'updated_at' => now()];

        $this->asTenant($a, function () use ($row) {
            ProbeNote::query()->insert([$row('insert')]);
            ProbeNote::insert([$row('static insert'), $row('static insert 2')]);
            ProbeNote::query()->insertGetId($row('insertGetId'));
            ProbeNote::query()->insertOrIgnore([$row('insertOrIgnore')]);
            ProbeNote::query()->fillAndInsert([['title' => 'fillAndInsert']]);
        });

        $this->assertDatabaseMissing('probe_notes', ['organization_id' => $b->id()]);
        $this->assertDatabaseCount('probe_notes', 6);
    }

    public function test_ac_a4_empty_bulk_writes_write_nothing(): void
    {
        [$a] = $this->createTenants();

        $this->asTenant($a, function () {
            $this->assertTrue(ProbeNote::query()->insert([]));
            $this->assertSame(0, ProbeNote::query()->insertOrIgnore([]));
            $this->assertSame(0, ProbeNote::query()->upsert([], ['organization_id', 'slug']));
        });

        $this->assertDatabaseCount('probe_notes', 0);
    }

    public function test_ac_a4_upsert_on_a_conflict_target_without_organization_id_is_refused(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Milik B', 'slug' => 'komodo']));

        foreach ([
            fn () => ProbeNote::query()->upsert([['id' => $foreign->id, 'title' => 'PWNED']], ['id'], ['title']),
            fn () => ProbeNote::query()->upsert([['id' => $foreign->id, 'title' => 'PWNED']], ['id']),
            fn () => ProbeNote::query()->upsert(['id' => $foreign->id, 'title' => 'PWNED'], 'id'),
        ] as $upsert) {
            try {
                $this->asTenant($a, $upsert);
                $this->fail('Upsert without organization_id in the conflict target should be refused.');
            } catch (BulkWriteBypassesTenancy) {
            }
        }

        $this->assertDatabaseHas('probe_notes', ['id' => $foreign->id, 'title' => 'Milik B', 'organization_id' => $b->id()]);
    }

    public function test_ac_a4_upsert_conflicts_stay_inside_the_current_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Milik B', 'slug' => 'komodo']));
        $own = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Lama', 'slug' => 'bromo']));

        $this->asTenant($a, function () {
            // Default update columns (null): organization_id must never be among them.
            ProbeNote::query()->upsert([
                ['slug' => 'komodo', 'title' => 'Baru A'],
                ['slug' => 'bromo', 'title' => 'Diubah A'],
            ], ['organization_id', 'slug']);

            // A single associative row with explicit update columns.
            ProbeNote::query()->upsert(['slug' => 'bromo', 'title' => 'Diubah lagi'], ['organization_id', 'slug'], ['title']);
        });

        $this->assertDatabaseHas('probe_notes', ['id' => $foreign->id, 'title' => 'Milik B', 'organization_id' => $b->id()]);
        $this->assertDatabaseHas('probe_notes', ['id' => $own->id, 'title' => 'Diubah lagi', 'organization_id' => $a->id()]);
        $this->assertDatabaseHas('probe_notes', ['slug' => 'komodo', 'title' => 'Baru A', 'organization_id' => $a->id()]);
    }

    public function test_ac_a4_upserts_cannot_update_organization_id(): void
    {
        [$a] = $this->createTenants();

        $this->expectException(OrganizationIdImmutable::class);

        $this->asTenant($a, fn () => ProbeNote::query()->upsert([['slug' => 'x', 'title' => 'x']], ['organization_id', 'slug'], ['organization_id']));
    }

    public function test_ac_a4_unscoped_writes_are_refused(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Milik B', 'slug' => 'komodo']));

        foreach ([
            'insertUsing' => fn () => ProbeNote::query()->insertUsing(['title'], ProbeNote::query()->select('title')),
            'updateOrInsert' => fn () => ProbeNote::query()->updateOrInsert(['id' => $foreign->id], ['title' => 'PWNED']),
            'updateFrom' => fn () => ProbeNote::query()->where('probe_notes.id', $foreign->id)->updateFrom(['title' => 'PWNED']),
            'truncate' => fn () => ProbeNote::query()->truncate(),
            'static truncate' => fn () => ProbeNote::truncate(),
        ] as $method => $write) {
            try {
                $this->asTenant($a, $write);
                $this->fail("{$method} should be refused.");
            } catch (BulkWriteBypassesTenancy) {
            }
        }

        $this->assertDatabaseHas('probe_notes', ['id' => $foreign->id, 'title' => 'Milik B', 'organization_id' => $b->id()]);
    }

    public function test_ac_a4_bulk_writes_without_context_must_name_the_organization(): void
    {
        [$a] = $this->createTenants();
        app(CurrentOrganization::class)->set(null);

        ProbeNote::query()->insert([['title' => 'sistem', 'organization_id' => $a->id()]]);
        $this->assertDatabaseHas('probe_notes', ['title' => 'sistem', 'organization_id' => $a->id()]);

        $this->expectException(MissingOrganizationContext::class);
        ProbeNote::query()->insert([['title' => 'tanpa organisasi']]);
    }

    public function test_ac_a4_query_builder_updates_cannot_change_organization_id(): void
    {
        [$a, $b] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'x']));

        foreach ([
            fn () => ProbeNote::query()->whereKey($note->id)->update(['organization_id' => $b->id()]),
            fn () => ProbeNote::query()->whereKey($note->id)->update(['probe_notes.organization_id' => $b->id()]),
            fn () => ProbeNote::query()->whereKey($note->id)->increment('organization_id'),
            fn () => ProbeNote::query()->whereKey($note->id)->increment('id', 0, ['organization_id' => $b->id()]),
        ] as $write) {
            try {
                $this->asTenant($a, $write);
                $this->fail('Changing organization_id through the query builder should throw.');
            } catch (OrganizationIdImmutable) {
            }
        }

        $this->asTenant($a, fn () => ProbeNote::query()->whereKey($note->id)->update(['title' => 'baru']));
        $this->assertDatabaseHas('probe_notes', ['id' => $note->id, 'title' => 'baru', 'organization_id' => $a->id()]);
    }

    public function test_ac_a2_force_delete_stays_inside_the_current_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Milik B']));
        $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Milik A']));

        $this->asTenant($a, function () use ($foreign, $b) {
            $this->assertSame(0, ProbeNote::query()->whereKey($foreign->id)->forceDelete());
            $this->assertSame(1, ProbeNote::query()->forceDelete());
            $this->assertSame(0, $b->organization->members()->forceDelete());
            $this->assertSame(0, $b->owner->memberships()->forceDelete());
        });

        $this->assertDatabaseHas('probe_notes', ['id' => $foreign->id]);
        $this->assertDatabaseMissing('probe_notes', ['organization_id' => $a->id()]);
        $this->assertDatabaseHas('organization_members', ['organization_id' => $b->id()]);
    }

    public function test_ac_a2_force_delete_on_soft_deleting_models_stays_inside_the_current_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($b, function () {
            $note = ProbeTrashableNote::create(['title' => 'Milik B']);
            $note->delete();

            return $note;
        });
        $own = $this->asTenant($a, function () {
            $note = ProbeTrashableNote::create(['title' => 'Milik A']);
            $note->delete();

            return $note;
        });

        $this->asTenant($a, function () use ($foreign) {
            $this->assertSame(0, ProbeTrashableNote::withTrashed()->whereKey($foreign->id)->forceDelete());
            $this->assertSame(1, ProbeTrashableNote::onlyTrashed()->forceDelete());
        });

        $this->assertDatabaseHas('probe_trashable_notes', ['id' => $foreign->id]);
        $this->assertDatabaseMissing('probe_trashable_notes', ['id' => $own->id]);
    }

    public function test_ac_a8_allowlisted_system_code_can_still_force_delete_across_organizations(): void
    {
        [$a, $b] = $this->createTenants();
        $this->asTenant($a, fn () => ProbeNote::create(['title' => 'A']));
        $this->asTenant($b, fn () => ProbeNote::create(['title' => 'B']));
        app(CurrentOrganization::class)->set(null);

        $this->assertSame(2, ProbeNote::acrossOrganizations()->forceDelete());
    }

    public function test_ac_a2_hand_made_or_hydrated_models_cannot_write_to_another_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($b, fn () => ProbeNote::create(['title' => 'Milik B']));
        $trashed = $this->asTenant($b, fn () => ProbeTrashableNote::create(['title' => 'Milik B']));

        $this->asTenant($a, function () use ($foreign, $trashed) {
            $forged = (new ProbeNote)->newFromBuilder(['id' => $foreign->id, 'organization_id' => $foreign->organization_id, 'title' => 'Milik B']);
            $forged->title = 'PWNED';
            $forged->save();
            $forged->touch();
            $forged->delete();

            $hydrated = ProbeNote::hydrate([['id' => $foreign->id, 'title' => 'x', 'organization_id' => $foreign->organization_id]])->first();
            $hydrated->update(['title' => 'PWNED']);
            $this->assertNull($hydrated->fresh());

            $forgedTrash = (new ProbeTrashableNote)->newFromBuilder(['id' => $trashed->id, 'organization_id' => $trashed->organization_id, 'title' => 'x']);
            $forgedTrash->forceDelete();
            $forgedTrash->restore();
        });

        $this->assertDatabaseHas('probe_notes', ['id' => $foreign->id, 'title' => 'Milik B']);
        $this->assertDatabaseHas('probe_trashable_notes', ['id' => $trashed->id, 'deleted_at' => null]);
    }

    public function test_ac_a2_relation_raw_updates_keep_the_organization_scope(): void
    {
        [$a, $b] = $this->createTenants();

        $this->asTenant($a, fn () => $b->organization->members()->rawUpdate(['role' => 'owner', 'updated_at' => now()->subYear()]));

        $this->assertSame(0, OrganizationMember::acrossOrganizations()
            ->where('organization_id', $b->id())
            ->where('updated_at', '<', now()->subMonth())
            ->count());
    }

    public function test_ac_a2_owners_still_work_with_their_own_models_by_key(): void
    {
        [$a] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Lama']));

        $this->asTenant($a, function () use ($note) {
            $note->update(['title' => 'Baru']);
            $this->assertSame('Baru', $note->fresh()->title);
            $note->delete();
        });

        // Without a context (system job) the model's own organization is used.
        $other = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Sistem']));
        app(CurrentOrganization::class)->set(null);
        $other->update(['title' => 'Diubah sistem']);

        $this->assertDatabaseMissing('probe_notes', ['id' => $note->id]);
        $this->assertDatabaseHas('probe_notes', ['id' => $other->id, 'title' => 'Diubah sistem']);
    }
}
