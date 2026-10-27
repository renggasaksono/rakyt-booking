<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminBookingFilters;
use App\Admin\AdminBookingPresenter;
use App\Admin\Queries\AdminBookingQueries;
use App\Admin\Queries\AuditEventQueries;
use App\Bookings\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin booking search and booking detail (F-013): bookings across every
 * organization, searchable by reference, customer name or phone and seller,
 * filterable by status, seller and departure date. Behind `auth` +
 * `platform-admin` (AC-6). Read-only — no edits, status changes, refunds,
 * cancellations or messages (spec Out list), so GET routes only.
 */
class AdminBookingController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(
        private AdminBookingQueries $queries,
        private AdminBookingPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $filters = AdminBookingFilters::fromRequest($request, $this->queries->organizationExists(...));
        $query = $filters->query();
        $now = CarbonImmutable::now();

        $rows = $this->queries->search($filters, ($filters->page - 1) * self::PER_PAGE, self::PER_PAGE + 1);
        $hasNext = $rows->count() > self::PER_PAGE;
        $organization = $this->queries->organization($filters->organization);

        return $this->private(Inertia::render('Admin/Bookings/Index', [
            'bookings' => $rows->take(self::PER_PAGE)
                ->map(fn (Booking $booking) => $this->presenter->row($booking, $now, $query))
                ->values()->all(),
            'filters' => $filters->toArray(),
            // The organization filter is F-015's searchable picker over the
            // F-012 seller lookup; only the chosen organization is sent.
            'selected_organization' => $organization === null ? null : [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'search_kind' => $filters->searchKind(),
            'options' => [
                'statuses' => array_map(
                    fn (BookingStatus $status) => ['value' => $status->value, 'label' => __('booking.status.'.$status->value)],
                    BookingStatus::cases(),
                ),
            ],
            'pagination' => [
                'page' => $filters->page,
                'next_url' => $hasNext ? route('admin.bookings.index', [...$query, 'halaman' => $filters->page + 1]) : null,
                'prev_url' => $filters->page > 1 ? route('admin.bookings.index', [...$query, ...($filters->page > 2 ? ['halaman' => $filters->page - 1] : [])]) : null,
            ],
            'copy' => [
                ...__('admin.bookings'),
                'picker' => __('admin.audit.filters'),
            ],
            'urls' => [
                'index' => route('admin.bookings.index'),
                'organization_lookup' => route('admin.sellers.lookup'),
            ],
        ])->toResponse($request));
    }

    public function show(Request $request, string $booking, AuditEventQueries $auditEvents): Response
    {
        $booking = $this->queries->find($booking) ?? abort(404);
        // The list's filters ride along, so "←" restores them (AC-3).
        $filters = AdminBookingFilters::fromRequest($request, $this->queries->organizationExists(...));

        return $this->private(Inertia::render('Admin/Bookings/Show', [
            ...$this->presenter->detail($booking, $auditEvents->forSubject($booking), CarbonImmutable::now()),
            'copy' => [
                ...__('admin.bookings'),
                'seats' => __('admin.sellers.departures.seats'),
                'audit' => __('admin.audit'),
            ],
            'urls' => [
                'back' => route('admin.bookings.index', $filters->query()),
                'audit' => route('admin.audit.index', ['organisasi' => $booking->organization_id]),
            ],
        ])->toResponse($request));
    }

    /**
     * Cross-seller data: never cached or stored (as the other admin pages).
     */
    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
