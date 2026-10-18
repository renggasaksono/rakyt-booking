<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\AutosaveTripRequest;
use App\Http\Requests\Trips\StoreTripRequest;
use App\Http\Requests\Trips\UpdateTripRequest;
use App\Models\Trip;
use App\Tenancy\CurrentOrganization;
use App\Trips\TripPresenter;
use App\Trips\TripPreview;
use App\Trips\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller trip pages (F-003). Every trip is resolved through the organization
 * scope, so another seller's trip is a 404 before the policy runs.
 */
class TripController extends Controller
{
    public function __construct(
        private CurrentOrganization $current,
        private TripService $trips,
        private TripPresenter $presenter,
    ) {}

    public function index(): Response
    {
        $trips = Trip::query()->orderByDesc('updated_at')->orderByDesc('id')->get();

        return Inertia::render('Trips/Index', [
            'trips' => $this->presenter->list($trips, $this->current->get()),
            'urls' => [
                'create' => route('trips.create'),
                'upcoming' => route('departures.upcoming'),
            ],
            'copy' => __('trips'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Trips/Edit', [
            ...$this->presenter->editor(null, $this->current->get()),
            'copy' => __('trips'),
        ]);
    }

    /**
     * Creates the draft on the first autosave (JSON) or a plain form post (AC-2).
     */
    public function store(StoreTripRequest $request): JsonResponse|RedirectResponse
    {
        $trip = $this->trips->create($request->fields());

        if ($request->expectsJson()) {
            return response()->json([
                ...$this->autosavePayload($trip, []),
                'editor' => $this->presenter->editor($trip, $this->current->get()),
            ], 201);
        }

        return redirect()->route('trips.edit', $trip);
    }

    public function edit(Trip $trip): Response
    {
        Gate::authorize('view', $trip);

        return Inertia::render('Trips/Edit', [
            ...$this->presenter->editor($trip, $this->current->get()),
            'copy' => __('trips'),
        ]);
    }

    /**
     * Draft autosave (AC-3). Published trips save only through update() (AC-4).
     */
    public function autosave(AutosaveTripRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        if ($trip->isPublished()) {
            return response()->json(['message' => __('trips.errors.published_autosave')], 409);
        }

        $notices = $this->trips->update($trip, $request->fields());

        return response()->json($this->autosavePayload($trip->refresh(), $notices));
    }

    /**
     * "Simpan perubahan" (AC-4).
     */
    public function update(UpdateTripRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);

        $notices = $this->trips->update($trip, $request->fields());

        return back()->with('toast', __('trips.notices.saved'))->with('notice', $notices === [] ? null : implode(' ', $notices));
    }

    public function publish(Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);

        $this->trips->publish($trip);

        return back()->with('toast', __('trips.notices.published'));
    }

    public function unpublish(Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);

        $this->trips->unpublish($trip);

        return back()->with('toast', __('trips.notices.unpublished'));
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        Gate::authorize('delete', $trip);

        $this->trips->delete($trip);

        return redirect()->route('trips.index')->with('toast', __('trips.notices.deleted'));
    }

    /**
     * @param  list<string>  $notices
     * @return array<string, mixed>
     */
    private function autosavePayload(Trip $trip, array $notices): array
    {
        $organization = $this->current->get();
        $trip->setRelation('organization', $organization);

        return [
            'trip' => $this->presenter->trip($trip),
            'saved_at' => CarbonImmutable::now($organization->timezone)->format('H.i'),
            'notices' => $notices,
            'checklist' => $this->presenter->checklist($trip, $organization),
            'preview' => TripPreview::for($trip),
            'departures' => $this->presenter->departureGroups($trip),
        ];
    }
}
