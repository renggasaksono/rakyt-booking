<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Queries\SellerDirectoryQueries;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Organizations\OrganizationSuspension;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Suspend and reactivate a seller organization (F-014) from the "Akun"
 * section of its admin detail page. Behind `auth` + `platform-admin`; the
 * page asks for confirmation and a reason, and the reason is required here
 * too (AC-1). A repeated submit (second tab, double tap) changes nothing,
 * writes no audit event and says so.
 */
class AdminSellerStatusController extends Controller
{
    public const REASON_MAX = 500;

    public function __construct(
        private SellerDirectoryQueries $queries,
        private OrganizationSuspension $suspension,
    ) {}

    public function suspend(Request $request, string $organization): RedirectResponse
    {
        $model = $this->organization($organization);
        $reason = $this->reason($request);

        $changed = $this->suspension->suspend($model, $request->user(), $reason);

        return $this->back($model, $changed
            ? ['toast' => __('admin.sellers.suspension.suspended', ['name' => $model->name])]
            : ['notice' => __('admin.sellers.suspension.already_suspended')]);
    }

    public function reactivate(Request $request, string $organization): RedirectResponse
    {
        $model = $this->organization($organization);
        $reason = $this->reason($request);

        $changed = $this->suspension->reactivate($model, $request->user(), $reason);

        return $this->back($model, $changed
            ? ['toast' => __('admin.sellers.suspension.reactivated', ['name' => $model->name])]
            : ['notice' => __('admin.sellers.suspension.already_active')]);
    }

    private function organization(string $id): Organization
    {
        return $this->queries->find($id) ?? abort(404);
    }

    private function reason(Request $request): string
    {
        $reason = $request->input('alasan');

        $data = validator(
            ['alasan' => is_string($reason) ? trim($reason) : $reason],
            ['alasan' => ['required', 'string', 'max:'.self::REASON_MAX]],
            [
                'alasan.required' => __('admin.sellers.suspension.reason_required'),
                'alasan.max' => __('admin.sellers.suspension.reason_max', ['max' => self::REASON_MAX]),
            ],
        )->validate();

        return $data['alasan'];
    }

    /**
     * @param  array<string, string>  $flash
     */
    private function back(Organization $organization, array $flash): RedirectResponse
    {
        $response = redirect()->route('admin.sellers.show', $organization->id, 303);

        foreach ($flash as $key => $message) {
            $response->with($key, $message);
        }

        return $response;
    }
}
