<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\DepartureRequest;
use App\Http\Requests\Trips\QuickEditDepartureRequest;
use App\Http\Requests\Trips\UpdateDepartureRequest;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Tenancy\CurrentOrganization;
use App\Trips\DepartureService;
use App\Trips\PublishChecklist;
use App\Trips\TripPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Departures of a trip (AC-17–AC-34). Nested routes use scopeBindings(), so a
 * departure id from another trip — or another seller — is a 404.
 */
class TripDepartureController extends Controller
{
    public function __construct(
        private CurrentOrganization $current,
        private DepartureService $departures,
        private PublishChecklist $checklist,
        private TripPresenter $presenter,
    ) {}

    /**
     * Upcoming departures across trips, for the phone quick-edit list (1g, AC-31, AC-42).
     */
    public function upcoming(): Response
    {
        $organization = $this->current->get();
        $today = TripDeparture::today($organization);

        $departures = TripDeparture::query()
            ->with('trip')
            ->whereDate('start_date', '>=', $today)
            ->orderBy('start_date')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(fn (TripDeparture $departure) => $departure->trip->setRelation('organization', $organization));

        return Inertia::render('Departures/Upcoming', [
            'departures' => $departures->map(fn (TripDeparture $departure) => $this->presenter->departure($departure))->all(),
            'context' => $this->presenter->context($organization),
            'urls' => ['index' => route('trips.index')],
            'copy' => __('trips'),
        ]);
    }

    public function store(DepartureRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);

        $this->departures->create($trip, $request->payload());

        return back()->with('toast', __('trips.notices.departure_saved'));
    }

    /**
     * "Salin dari tanggal sebelumnya" (AC-25).
     */
    public function copyTemplate(Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);
        $trip->setRelation('organization', $this->current->get());

        $template = $this->departures->copyTemplate($trip);
        abort_if($template === null, 404);

        return response()->json($template);
    }

    public function update(UpdateDepartureRequest $request, Trip $trip, TripDeparture $departure): RedirectResponse
    {
        Gate::authorize('update', $departure);

        $this->departures->update($departure, $request->payload());

        return $this->afterChange($trip);
    }

    public function quickUpdate(QuickEditDepartureRequest $request, Trip $trip, TripDeparture $departure): RedirectResponse
    {
        Gate::authorize('update', $departure);
        $departure->setRelation('trip', $trip);

        $this->departures->quickEdit($departure, $request->payload());

        return $this->afterChange($trip);
    }

    public function destroy(Trip $trip, TripDeparture $departure): RedirectResponse
    {
        Gate::authorize('delete', $departure);

        $this->departures->delete($departure);

        return $this->afterChange($trip, __('trips.notices.departure_deleted'));
    }

    /**
     * AC-30: a published scheduled trip without a bookable date stays published, with a notice.
     */
    private function afterChange(Trip $trip, ?string $toast = null): RedirectResponse
    {
        $trip->refresh()->setRelation('organization', $this->current->get());
        $response = back()->with('toast', $toast ?? __('trips.notices.saved'));

        if ($trip->isPublished() && $trip->isScheduled() && ! $this->checklist->hasUpcomingOpenDeparture($trip)) {
            $response->with('notice', __('trips.notices.last_departure'));
        }

        return $response;
    }
}
