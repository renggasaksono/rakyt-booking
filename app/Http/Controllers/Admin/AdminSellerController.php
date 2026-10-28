<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Queries\AuditEventQueries;
use App\Admin\Queries\SellerDirectoryQueries;
use App\Admin\SellerDirectoryFilters;
use App\Admin\SellerDirectoryPresenter;
use App\Http\Controllers\Controller;
use App\Media\PrivateMedia;
use App\Models\Organization;
use App\Organizations\OrganizationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin seller directory (F-012): every organization, searchable and
 * filterable, and one organization's detail. Behind `auth` +
 * `platform-admin` (AC-6). Read-only, except for F-014's suspend /
 * reactivate (AdminSellerStatusController) on the detail page's "Akun" section.
 */
class AdminSellerController extends Controller
{
    public const PER_PAGE = 25;

    public const LOOKUP_LIMIT = 10;

    public function __construct(
        private SellerDirectoryQueries $queries,
        private SellerDirectoryPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $filters = SellerDirectoryFilters::fromRequest($request);
        $query = $filters->query();

        $rows = $this->queries->search($filters, ($filters->page - 1) * self::PER_PAGE, self::PER_PAGE + 1);
        $hasNext = $rows->count() > self::PER_PAGE;

        return $this->private(Inertia::render('Admin/Sellers/Index', [
            'sellers' => $rows->take(self::PER_PAGE)->map(fn (Organization $organization) => $this->presenter->row($organization))->values()->all(),
            'filters' => $filters->toArray(),
            'options' => [
                'statuses' => array_map(fn (OrganizationStatus $status) => ['value' => $status->value, 'label' => $status->label()], OrganizationStatus::cases()),
                'trips' => [['value' => SellerDirectoryFilters::PUBLISHED_TRIPS, 'label' => __('admin.sellers.filters.published_trips')]],
            ],
            'pagination' => [
                'page' => $filters->page,
                'next_url' => $hasNext ? route('admin.sellers.index', [...$query, 'halaman' => $filters->page + 1]) : null,
                'prev_url' => $filters->page > 1 ? route('admin.sellers.index', [...$query, ...($filters->page > 2 ? ['halaman' => $filters->page - 1] : [])]) : null,
            ],
            'copy' => __('admin.sellers'),
            'urls' => [
                'index' => route('admin.sellers.index'),
            ],
        ])->toResponse($request));
    }

    /**
     * Organizations matching `cari`, for the audit view's organization picker.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = SellerDirectoryFilters::term($request->query('cari'));
        $organizations = $term === null ? collect() : $this->queries->lookup($term, self::LOOKUP_LIMIT);

        return $this->private(response()->json([
            'organizations' => $organizations
                ->map(fn (Organization $organization) => ['id' => (string) $organization->id, 'name' => $organization->name, 'slug' => $organization->slug])
                ->values()->all(),
        ]));
    }

    public function show(Request $request, string $organization, AuditEventQueries $auditEvents): Response
    {
        $organization = $this->queries->find($organization) ?? abort(404);

        return $this->private(Inertia::render('Admin/Sellers/Show', [
            ...$this->presenter->detail($organization, $auditEvents->forOrganization($organization)),
            'copy' => [
                ...__('admin.sellers'),
                'audit' => __('admin.audit'),
            ],
            'urls' => [
                'index' => route('admin.sellers.index'),
                'audit' => route('admin.audit.index', ['organisasi' => $organization->id]),
                // F-014: the account-status actions.
                'suspend' => route('admin.sellers.suspend', $organization->id),
                'reactivate' => route('admin.sellers.reactivate', $organization->id),
            ],
            // F-014: the reason limit the status actions validate, so the form matches.
            'reason_max' => AdminSellerStatusController::REASON_MAX,
        ])->toResponse($request));
    }

    /**
     * AC-7: issues the admin a short-lived signed URL for a booking's payment
     * evidence, so a link on an open page never goes stale and the file URL
     * itself expires within minutes.
     */
    public function evidence(string $organization, string $booking): RedirectResponse
    {
        $organization = $this->queries->find($organization) ?? abort(404);
        abort_if($this->queries->evidencePath($organization, $booking) === null, 404);

        $ttl = min(5, (int) config('rakyt.media.private_url_ttl'));

        return $this->private(redirect()->to(URL::temporarySignedRoute(
            'admin.sellers.evidence.show',
            now()->addMinutes($ttl),
            ['organization' => $organization->id, 'booking' => $booking],
        )));
    }

    /**
     * AC-7: streams the evidence from the private disk (F-006's storage) to a
     * valid signed URL, behind the admin gate.
     */
    public function showEvidence(string $organization, string $booking): Response
    {
        $organization = $this->queries->find($organization) ?? abort(404);
        $path = $this->queries->evidencePath($organization, $booking);

        abort_if($path === null || ! PrivateMedia::exists($path), 404);

        return PrivateMedia::response($path);
    }

    /**
     * Cross-seller data: never cached or stored (as the other admin pages).
     *
     * @template TResponse of Response
     *
     * @param  TResponse  $response
     * @return TResponse
     */
    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
