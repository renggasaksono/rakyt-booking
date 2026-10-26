<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AuditEventFilters;
use App\Admin\AuditEventPresenter;
use App\Admin\Queries\AuditEventQueries;
use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Organization;
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
        $organizations = $this->queries->organizations();
        $filters = AuditEventFilters::fromRequest($request, $organizations->modelKeys());
        $query = $filters->query();

        $rows = $this->queries->search($filters, ($filters->page - 1) * self::PER_PAGE, self::PER_PAGE + 1);
        $hasNext = $rows->count() > self::PER_PAGE;

        $response = Inertia::render('Admin/AuditLog', [
            'events' => $this->presenter->rows($rows->take(self::PER_PAGE)),
            'filters' => $filters->toArray(),
            'options' => [
                'organizations' => $organizations
                    ->map(fn (Organization $organization) => ['id' => (string) $organization->id, 'name' => $organization->name, 'slug' => $organization->slug])
                    ->values()->all(),
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
            ],
        ])->toResponse($request);

        // Cross-seller data: never cached or stored (as the admin dashboard).
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
