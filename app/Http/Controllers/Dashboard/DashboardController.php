<?php

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\DashboardPresenter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Hari ini" (F-007 section B): where sellers land after sign-in and onboarding (AC-1).
 */
class DashboardController extends Controller
{
    public function __construct(
        private CurrentOrganization $current,
        private DashboardPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $response = Inertia::render('Dashboard/Today', $this->presenter->props($this->current->get(), $request->user()))
            ->toResponse($request);
        // Customer names on the page: never kept by a shared cache or the back/forward cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
