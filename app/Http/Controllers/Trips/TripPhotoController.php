<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\ReorderTripPhotosRequest;
use App\Http\Requests\Trips\StoreTripPhotoRequest;
use App\Models\Trip;
use App\Models\TripPhoto;
use App\Tenancy\CurrentOrganization;
use App\Trips\TripPhotoService;
use App\Trips\TripPresenter;
use App\Trips\TripPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Trip photos (AC-7). JSON so the editor can show per-photo progress.
 */
class TripPhotoController extends Controller
{
    public function __construct(
        private CurrentOrganization $current,
        private TripPhotoService $photos,
        private TripPresenter $presenter,
    ) {}

    public function store(StoreTripPhotoRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $photo = $this->photos->add($trip, $request->file('photo'));

        return response()->json([...$this->payload($trip), 'photo_id' => $photo->public_id], 201);
    }

    public function reorder(ReorderTripPhotosRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $this->photos->reorder($trip, $request->photoIds());

        return response()->json($this->payload($trip));
    }

    public function destroy(Trip $trip, TripPhoto $photo): JsonResponse
    {
        Gate::authorize('update', $trip);
        Gate::authorize('delete', $photo);

        $this->photos->remove($photo);

        return response()->json($this->payload($trip));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Trip $trip): array
    {
        $trip->setRelation('organization', $this->current->get());

        return [
            'photos' => $this->presenter->photos($trip),
            'checklist' => $this->presenter->checklist($trip, $this->current->get()),
            'preview' => TripPreview::for($trip),
        ];
    }
}
