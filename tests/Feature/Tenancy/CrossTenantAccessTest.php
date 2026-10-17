<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Tenancy\ProbeNote;
use Tests\Fixtures\Tenancy\RegistersProbeNotes;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 A2, A3 and the A9 harness, exercised on the probe resource.
 */
class CrossTenantAccessTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RegistersProbeNotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerProbeNotes();
    }

    public function test_ac_a2_another_organizations_record_is_404_for_every_action_and_unchanged(): void
    {
        [$a, $b] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Rahasia A']));

        $this->assertCrossTenantNotFound($b, [
            ['GET', "/probe-notes/{$note->id}"],
            ['PUT', "/probe-notes/{$note->id}", ['title' => 'Diubah B']],
            ['DELETE', "/probe-notes/{$note->id}"],
            ['POST', "/probe-notes/{$note->id}/archive"],
        ], fn () => $this->assertDatabaseHas('probe_notes', [
            'id' => $note->id,
            'title' => 'Rahasia A',
            'archived' => false,
        ]));
    }

    public function test_ac_a2_owner_can_use_their_own_record(): void
    {
        [$a] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Milik A']));

        $this->actingAsTenant($a)->putJson("/probe-notes/{$note->id}", ['title' => 'Baru'])->assertOk();

        $this->assertDatabaseHas('probe_notes', ['id' => $note->id, 'title' => 'Baru']);
    }

    public function test_ac_a2_policy_answers_404_not_403_even_if_the_scope_is_bypassed(): void
    {
        [$a, $b] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'A']));

        app(CurrentOrganization::class)->set($b->organization);
        $response = Gate::forUser($b->owner)->inspect('update', $note);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_ac_a3_payload_referencing_another_organizations_record_is_rejected(): void
    {
        [$a, $b] = $this->createTenants();
        $foreign = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Milik A']));

        $this->assertCrossTenantPayloadRejected(
            $b,
            'POST',
            '/probe-notes',
            ['title' => 'Catatan B', 'related_note_id' => $foreign->id],
            'related_note_id',
            fn () => $this->assertDatabaseMissing('probe_notes', ['title' => 'Catatan B']),
        );
    }

    public function test_ac_a3_payload_referencing_own_record_is_accepted(): void
    {
        [$a] = $this->createTenants();
        $own = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Induk']));

        $this->actingAsTenant($a)
            ->postJson('/probe-notes', ['title' => 'Anak', 'related_note_id' => $own->id])
            ->assertCreated();
    }

    public function test_non_numeric_ids_are_404_in_urls_and_422_in_payloads(): void
    {
        [$a] = $this->createTenants();

        $this->actingAsTenant($a)->getJson('/probe-notes/abc')->assertNotFound();
        $this->actingAsTenant($a)->getJson('/probe-notes/1abc')->assertNotFound();

        $this->actingAsTenant($a)
            ->postJson('/probe-notes', ['title' => 'x', 'related_note_id' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('related_note_id');
    }

    public function test_another_organization_itself_is_404_through_its_policy(): void
    {
        [$a, $b] = $this->createTenants();
        Route::middleware(['web', 'auth'])->get('/probe-org/{organization}', function (Organization $organization) {
            Gate::authorize('view', $organization);

            return ['id' => $organization->id];
        });

        $this->actingAsTenant($a)->getJson("/probe-org/{$a->id()}")->assertOk();
        $this->actingAsTenant($a)->getJson("/probe-org/{$b->id()}")->assertNotFound();
    }

    public function test_guests_cannot_reach_seller_routes(): void
    {
        [$a] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'A']));

        $this->getJson("/probe-notes/{$note->id}")->assertUnauthorized();
    }

    public function test_signed_in_user_without_organization_sees_nothing(): void
    {
        [$a] = $this->createTenants();
        $note = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'A']));

        $this->actingAs(User::factory()->create())
            ->getJson("/probe-notes/{$note->id}")
            ->assertNotFound();
    }
}
