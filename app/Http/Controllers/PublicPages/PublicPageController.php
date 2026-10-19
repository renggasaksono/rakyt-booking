<?php

namespace App\Http\Controllers\PublicPages;

use App\Http\Controllers\Controller;
use App\PublicPages\PublicPagePresenter;
use App\PublicPages\PublicVisibility;
use App\Tenancy\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public booking pages rakyt.id/<slug> and rakyt.id/<slug>/<trip> (F-002).
 * The organization comes from the slug (ResolvePublicOrganization), never
 * from the signed-in user; head tags are rendered by the server (ADR-0024).
 */
class PublicPageController extends Controller
{
    public function __construct(
        private CurrentOrganization $current,
        private PublicVisibility $visibility,
        private PublicPagePresenter $presenter,
    ) {}

    public function seller(): Response
    {
        ['props' => $props, 'head' => $head] = $this->presenter->seller($this->current->get());

        return Inertia::render('Public/Seller', $props)->withViewData('publicHead', $head);
    }

    public function trip(string $organization, string $trip): Response
    {
        // AC-5, AC-6: published trips of this organization only; anything else is the same 404.
        $model = $this->visibility->trips()->where('slug', $trip)->with('photos')->first();

        abort_if($model === null, 404);

        ['props' => $props, 'head' => $head] = $this->presenter->trip($this->current->get(), $model);

        return Inertia::render('Public/Trip', $props)->withViewData('publicHead', $head);
    }
}
