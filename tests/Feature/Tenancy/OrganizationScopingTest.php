<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\CurrentOrganization;
use App\Tenancy\Exceptions\BulkWriteBypassesTenancy;
use App\Tenancy\Exceptions\MissingOrganizationContext;
use App\Tenancy\Exceptions\OrganizationIdImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Tenancy\ProbeNote;
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

    public function test_ac_a4_bulk_inserts_and_upserts_cannot_write_into_another_organization(): void
    {
        [$a, $b] = $this->createTenants();
        $row = fn (string $title) => ['title' => $title, 'organization_id' => $b->id(), 'created_at' => now(), 'updated_at' => now()];

        $this->asTenant($a, function () use ($row) {
            ProbeNote::query()->insert([$row('insert')]);
            ProbeNote::insert([$row('static insert'), $row('static insert 2')]);
            ProbeNote::query()->insertGetId($row('insertGetId'));
            ProbeNote::query()->insertOrIgnore([$row('insertOrIgnore')]);
            ProbeNote::query()->upsert([$row('upsert')], ['id'], ['title']);
            ProbeNote::query()->fillAndInsert([['title' => 'fillAndInsert']]);
        });

        $this->assertDatabaseMissing('probe_notes', ['organization_id' => $b->id()]);
        $this->assertDatabaseCount('probe_notes', 7);
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

    public function test_ac_a4_inserts_from_a_select_are_refused(): void
    {
        [$a] = $this->createTenants();

        $this->expectException(BulkWriteBypassesTenancy::class);

        $this->asTenant($a, fn () => ProbeNote::query()->insertUsing(['title'], ProbeNote::query()->select('title')));
    }

    public function test_ac_a4_upserts_cannot_update_organization_id(): void
    {
        [$a, $b] = $this->createTenants();

        $this->expectException(OrganizationIdImmutable::class);

        $this->asTenant($a, fn () => ProbeNote::query()->upsert([['id' => 1, 'title' => 'x']], ['id'], ['organization_id']));
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
}
