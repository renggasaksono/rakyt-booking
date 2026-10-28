<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Queries\AdminOverviewQueries;
use App\Admin\SellerDirectoryFilters;
use App\Bookings\BookingStatus;
use App\Http\Controllers\Controller;
use App\Organizations\OrganizationStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin overview (F-011): five live counts across every organization,
 * each linking to the matching filtered list of the seller directory (F-012)
 * or the booking search (F-013). Behind `auth` + `platform-admin`.
 */
class AdminDashboardController extends Controller
{
    public function __construct(private AdminOverviewQueries $queries) {}

    public function index(Request $request): Response
    {
        $organizations = $this->queries->organizationsByStatus();
        $trips = $this->queries->publishedTrips();
        $bookings = $this->queries->bookingsByStatus();

        $response = Inertia::render('Admin/Dashboard', [
            'stats' => [
                'sellers' => [
                    ...$this->stat(array_sum($organizations), route('admin.sellers.index')),
                    // AC-4: suspended sellers are counted apart from active ones.
                    'active' => $this->sellers($organizations, OrganizationStatus::Active),
                    'suspended' => $this->sellers($organizations, OrganizationStatus::Suspended),
                ],
                // Trips are read per seller (F-012): the count opens the sellers that publish one.
                'published_trips' => $this->stat($trips, route('admin.sellers.index', ['trip' => SellerDirectoryFilters::PUBLISHED_TRIPS])),
                'bookings' => $this->stat(array_sum($bookings), route('admin.bookings.index')),
                'awaiting_payment' => $this->bookings($bookings, BookingStatus::AwaitingPayment),
                'needs_review' => $this->bookings($bookings, BookingStatus::NeedsReview),
            ],
            'copy' => __('admin'),
        ])->toResponse($request);

        // Cross-seller data: never cached or stored.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{count: int, url: string}
     */
    private function sellers(array $counts, OrganizationStatus $status): array
    {
        return $this->stat($counts[$status->value], route('admin.sellers.index', ['status' => $status->value]));
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{count: int, url: string}
     */
    private function bookings(array $counts, BookingStatus $status): array
    {
        return $this->stat($counts[$status->value], route('admin.bookings.index', ['status' => $status->value]));
    }

    /**
     * @return array{count: int, url: string}
     */
    private function stat(int $count, string $url): array
    {
        return ['count' => $count, 'url' => $url];
    }
}
