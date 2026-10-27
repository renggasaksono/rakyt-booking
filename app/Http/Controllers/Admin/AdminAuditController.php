<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AuditEventFilters;
use App\Admin\AuditEventPresenter;
use App\Admin\Queries\AuditEventQueries;
use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin audit view (F-015): every audit event, across organizations,
 * filterable by organization, actor and action. Read-only — audit events are
 * append-only, so there is no write route (AC-4). Behind `auth` +
 * `platform-admin` (AC-5).
 */
class AdminAuditController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(
        private AuditEventQueries $queries,
        private AuditEventPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $filters = AuditEventFilters::fromRequest($request, $this->queries->organizationExists(...));
        $query = $filters->query();

        $rows = $this->queries->search($filters, ($filters->page - 1) * self::PER_PAGE, self::PER_PAGE + 1);
        $hasNext = $rows->count() > self::PER_PAGE;

        $response = Inertia::render('Admin/AuditLog', [
            'events' => $this->presenter->rows($rows->take(self::PER_PAGE)),
            'filters' => $filters->toArray(),
            // The organization filter is a searchable picker over the F-012
            // seller lookup; only the chosen organization is sent.
            'selected_organization' => $this->selectedOrganization($filters),
            'options' => [
                'actor_types' => array_map(fn (string $type) => ['value' => $type, 'label' => __('admin.audit.actor_types.'.$type)], Actor::TYPES),
                'actions' => array_map(fn (AuditAction $action) => ['value' => $action->value, 'label' => __('admin.audit.actions.'.$action->value)], AuditAction::cases()),
            ],
            'pagination' => [
                'page' => $filters->page,
                'next_url' => $hasNext ? route('admin.audit.index', [...$query, 'halaman' => $filters->page + 1]) : null,
                'prev_url' => $filters->page > 1 ? route('admin.audit.index', [...$query, ...($filters->page > 2 ? ['halaman' => $filters->page - 1] : [])]) : null,
            ],
            'copy' => __('admin.audit'),
            'urls' => [
                'index' => route('admin.audit.index'),
                'organization_lookup' => route('admin.sellers.lookup'),
            ],
        ])->toResponse($request);

        // Cross-seller data: never cached or stored (as the admin dashboard).
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @return array{id: string, name: string, slug: string}|null
     */
    private function selectedOrganization(AuditEventFilters $filters): ?array
    {
        if ($filters->organization === null || $filters->organization === AuditEventFilters::PLATFORM) {
            return null;
        }

        $organization = $this->queries->organizationsById([(int) $filters->organization])->first();

        return $organization === null ? null : ['id' => (string) $organization->id, 'name' => $organization->name, 'slug' => $organization->slug];
    }
}
