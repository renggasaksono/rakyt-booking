<?php

namespace Tests\Fixtures\Tenancy;

use App\Tenancy\Rules\ExistsInCurrentOrganization;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the probe_notes table (inside the test transaction) and a typical
 * set of seller routes for it: index, show, store, update, delete and a custom action.
 */
trait RegistersProbeNotes
{
    protected function registerProbeNotes(): void
    {
        Schema::create('probe_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->foreignId('related_note_id')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('probe_trashable_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        Gate::policy(ProbeNote::class, ProbeNotePolicy::class);

        Route::middleware(['web', 'auth'])->prefix('probe-notes')->group(function () {
            Route::get('/', fn (Request $request) => [
                'titles' => ProbeNote::query()
                    ->when($request->query('q'), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
                    ->orderBy('id')
                    ->pluck('title'),
                'count' => ProbeNote::query()->count(),
            ]);

            Route::post('/', function (Request $request) {
                $data = $request->validate([
                    'title' => ['required', 'string'],
                    'related_note_id' => ['nullable', new ExistsInCurrentOrganization('probe_notes')],
                ]);

                // Deliberately passes the whole payload, incl. any organization_id (A4).
                return ProbeNote::create($request->all() + $data);
            });

            Route::get('/{note}', function (ProbeNote $note) {
                Gate::authorize('view', $note);

                return $note;
            });

            Route::put('/{note}', function (Request $request, ProbeNote $note) {
                Gate::authorize('update', $note);
                $note->update($request->only('title'));

                return $note;
            });

            Route::delete('/{note}', function (ProbeNote $note) {
                Gate::authorize('delete', $note);
                $note->delete();

                return response()->noContent();
            });

            Route::post('/{note}/archive', function (ProbeNote $note) {
                Gate::authorize('update', $note);
                $note->update(['archived' => true]);

                return $note;
            });
        });
    }
}
