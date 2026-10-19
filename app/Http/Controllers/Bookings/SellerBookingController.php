<?php

namespace App\Http\Controllers\Bookings;

use App\Bookings\BookingFilters;
use App\Bookings\BookingService;
use App\Bookings\BookingStatus;
use App\Bookings\SellerBookingPresenter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Organizations\BookingSlug;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The seller's bookings (F-007 sections C–E): the list `/pesanan`, the detail
 * `/pesanan/{public_id}` and the seller's cancellation. Route binding is
 * scoped to the current organization, so another seller's booking is a 404
 * before the policy runs (AC-34).
 */
class SellerBookingController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(
        private CurrentOrganization $current,
        private BookingService $service,
        private SellerBookingPresenter $presenter,
    ) {}

    /**
     * AC-16–AC-22.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $now = CarbonImmutable::now();
        $filters = BookingFilters::fromRequest($request);
        $query = $filters->query();

        $rows = $filters->apply(Booking::query()->with('departure.trip'), $now)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset(($filters->page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasNext = $rows->count() > self::PER_PAGE;
        $organization = $this->current->get();
        $departure = $filters->departure;

        return self::private(Inertia::render('Bookings/Index', [
            'bookings' => $rows->take(self::PER_PAGE)->map(fn (Booking $booking) => $this->presenter->item($booking, $now, $query))->values()->all(),
            'filters' => [
                'cari' => $filters->search,
                'status' => $filters->status,
                'trip' => $filters->trip?->public_id,
                'dari' => $filters->from,
                'sampai' => $filters->to,
                'keberangkatan' => $departure?->public_id,
            ],
            'options' => [
                'statuses' => array_map(fn (string $value) => ['value' => $value, 'label' => __('bookings.filters.'.$value)], BookingFilters::STATUSES),
                'trips' => Trip::query()->orderBy('name')->orderBy('id')->get(['public_id', 'name'])
                    ->map(fn (Trip $trip) => ['id' => $trip->public_id, 'name' => $trip->name])->values()->all(),
                'departure' => $departure === null ? null : [
                    'trip_name' => $departure->trip->name,
                    'start_date' => $departure->start_date->toDateString(),
                ],
            ],
            'pagination' => [
                'page' => $filters->page,
                'next_url' => $hasNext ? route('bookings.index', [...$query, 'halaman' => $filters->page + 1]) : null,
                'prev_url' => $filters->page > 1 ? route('bookings.index', [...$query, ...($filters->page > 2 ? ['halaman' => $filters->page - 1] : [])]) : null,
            ],
            // AC-21: "Belum ada pesanan." only when the seller has none at all.
            'has_any' => $rows->isNotEmpty() || Booking::query()->exists(),
            'context' => [
                'now' => $now->utc()->format('Y-m-d\TH:i:s\Z'),
                'timezone' => $organization->timezone,
                'today' => $now->setTimezone($organization->timezone)->toDateString(),
            ],
            'copy' => [
                ...__('bookings'),
                'status_words' => __('booking.status'),
                'calendar' => __('trips.calendar'),
                'link' => __('dashboard.link'),
            ],
            'urls' => [
                'index' => route('bookings.index'),
                'public_url' => BookingSlug::url($organization->slug),
            ],
        ])->toResponse($request));
    }

    /**
     * AC-23–AC-27.
     */
    public function show(Request $request, Booking $booking): Response
    {
        Gate::authorize('view', $booking);
        $booking->load('departure.trip');

        return self::private(Inertia::render('Bookings/Show', $this->presenter->detail(
            $this->current->get(),
            $booking,
            BookingFilters::fromRequest($request)->query(),
        ))->toResponse($request));
    }

    /**
     * AC-28–AC-33: the seller cancels. A booking that is no longer holding seats
     * — released by the job, cancelled in another tab — is left alone (AC-30).
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $participants = $booking->participants;
        $back = route('bookings.show', [$booking, ...BookingFilters::fromRequest($request)->query()]);

        if ($this->service->cancelBySeller($booking, $request->user())) {
            return redirect($back)->with('toast', __('bookings.cancel.done', ['n' => $participants]));
        }

        return redirect($back)->with('notice', __('bookings.show.inactive'));
    }

    /**
     * AC-25 on Hari ini rows: "Ingatkan via WA" goes through here, so the
     * customer link — and its token — never sits in Hari ini's props
     * (ADR-0029 §1). Without an active hold it goes to the detail instead.
     */
    public function remind(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);
        $booking->load('departure.trip');
        $now = CarbonImmutable::now();

        if ($booking->status !== BookingStatus::AwaitingPayment || ! $booking->holdIsActive($now)) {
            return redirect()->route('bookings.show', $booking)->with('notice', __('bookings.show.inactive'));
        }

        $response = redirect()->away($this->presenter->reminderUrl($this->current->get(), $booking, $now));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
