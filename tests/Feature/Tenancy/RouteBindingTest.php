<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Tenancy\ProbeNote;
use Tests\Fixtures\Tenancy\ProbeTrashableNote;
use Tests\Fixtures\Tenancy\RegistersProbeNotes;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 R15 / R16: non-numeric ids in withTrashed() and scopeBindings() routes
 * are a 404, not a PostgreSQL error (500); scoping still applies.
 */
class RouteBindingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RegistersProbeNotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerProbeNotes();

        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/probe-trash/{note}', fn (ProbeTrashableNote $note) => ['id' => $note->id])->withTrashed();
            Route::get('/probe-parent/{note}/children/{child}', fn (ProbeNote $note, ProbeNote $child) => ['id' => $child->id])->scopeBindings();
            Route::get('/probe-parent-trash/{note}/children/{child}', fn (ProbeNote $note, ProbeNote $child) => ['id' => $child->id])->scopeBindings()->withTrashed();
        });
    }

    public function test_r15_soft_deletable_bindings_reject_malformed_ids_and_stay_scoped(): void
    {
        [$a, $b] = $this->createTenants();
        $mine = $this->asTenant($a, function () {
            $note = ProbeTrashableNote::create(['title' => 'A']);
            $note->delete();

            return $note;
        });
        $foreign = $this->asTenant($b, fn () => ProbeTrashableNote::create(['title' => 'B']));

        foreach (['abc', '1abc', '99999999999999999999', '0'] as $bad) {
            $this->actingAsTenant($a)->getJson("/probe-trash/{$bad}")->assertNotFound();
        }

        $this->actingAsTenant($a)->getJson("/probe-trash/{$mine->id}")->assertOk()->assertJson(['id' => $mine->id]);
        $this->actingAsTenant($a)->getJson("/probe-trash/{$foreign->id}")->assertNotFound();
    }

    public function test_r16_child_bindings_reject_malformed_ids_and_stay_scoped(): void
    {
        [$a, $b] = $this->createTenants();
        $parent = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Induk']));
        $child = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Anak', 'related_note_id' => $parent->id]));
        $stranger = $this->asTenant($a, fn () => ProbeNote::create(['title' => 'Lain']));
        $foreign = $this->asTenant($b, fn () => ProbeNote::create(['title' => 'B', 'related_note_id' => $parent->id]));

        foreach (['/probe-parent', '/probe-parent-trash'] as $prefix) {
            foreach (['abc', '1abc', '99999999999999999999'] as $bad) {
                $this->actingAsTenant($a)->getJson("{$prefix}/{$parent->id}/children/{$bad}")->assertNotFound();
            }

            $this->actingAsTenant($a)->getJson("{$prefix}/{$parent->id}/children/{$child->id}")->assertOk()->assertJson(['id' => $child->id]);
            $this->actingAsTenant($a)->getJson("{$prefix}/{$parent->id}/children/{$stranger->id}")->assertNotFound();
            $this->actingAsTenant($a)->getJson("{$prefix}/{$parent->id}/children/{$foreign->id}")->assertNotFound();
        }
    }
}
