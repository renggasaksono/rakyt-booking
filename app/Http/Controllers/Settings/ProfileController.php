<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Media\UploadRules;
use App\Organizations\BookingSlug;
use App\Organizations\OrganizationProfileService;
use App\Organizations\OrganizationRules;
use App\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Profil usaha (F-001 F1, F2, F4). Always the signed-in
 * seller's own organization (T1): there is no organization id in the URL.
 */
class ProfileController extends Controller
{
    public const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    public function __construct(
        private CurrentOrganization $current,
        private OrganizationProfileService $profiles,
    ) {}

    public function edit(): Response
    {
        $organization = $this->current->get();
        Gate::authorize('view', $organization);

        return Inertia::render('Settings/Profile', [
            'profile' => [
                'name' => $organization->name,
                'city' => $organization->city,
                'whatsapp' => str_starts_with($organization->whatsapp, '+62') ? substr($organization->whatsapp, 3) : $organization->whatsapp,
                'slug' => $organization->slug,
                'bio' => $organization->bio,
                'timezone' => $organization->timezone,
                'logo_url' => $organization->logoUrl(),
            ],
            'slugLocked' => $organization->slugLocked(),
            'publicHost' => config('rakyt.public_host'),
            'timezones' => self::TIMEZONES,
            'limits' => [
                'name' => OrganizationRules::NAME_MAX,
                'city' => OrganizationRules::CITY_MAX,
                'bio' => OrganizationRules::BIO_MAX,
                'slug' => BookingSlug::MAX,
                'logo_kb' => (int) config('rakyt.media.logo.max_kb'),
                'logo_types' => config('rakyt.media.logo.mimetypes'),
            ],
            'urls' => [
                'update' => route('settings.profile'),
                'logo' => route('settings.profile.logo'),
                'slug' => route('onboarding.slug'),
                'back' => route('settings.index'),
            ],
            'copy' => __('profile'),
            'onboardingCopy' => __('onboarding.organization'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->current->get();
        Gate::authorize('update', $organization);

        $withSlug = ! $organization->slugLocked();
        $fields = ['name', 'city', 'whatsapp', 'bio', 'timezone', ...($withSlug ? ['slug'] : [])];
        $input = OrganizationRules::normalize($request->only($fields));

        $data = Validator::make($input, [
            ...OrganizationRules::rules($organization, $withSlug),
            'bio' => ['nullable', 'string', 'max:'.OrganizationRules::BIO_MAX],
            'timezone' => ['required', Rule::in(self::TIMEZONES)],
        ], OrganizationRules::messages(), OrganizationRules::attributes())->validate();

        $data['bio'] ??= null;
        $recomputed = $this->profiles->update($organization, $data);

        return back()
            ->with('toast', __('profile.saved'))
            ->with('notice', $recomputed > 0 ? __('profile.timezone_notice', ['n' => $recomputed]) : null);
    }

    public function storeLogo(Request $request): RedirectResponse
    {
        $organization = $this->current->get();
        Gate::authorize('update', $organization);

        $request->validate(['logo' => UploadRules::logo()], [
            'logo.mimetypes' => __('profile.logo_type'),
            'logo.file' => __('profile.logo_type'),
            'logo.uploaded' => __('profile.logo_size'),
            'logo.max' => __('profile.logo_size'),
        ], ['logo' => __('profile.logo')]);

        $this->profiles->replaceLogo($organization, $request->file('logo'));

        return back()->with('toast', __('profile.logo_saved'));
    }

    public function destroyLogo(): RedirectResponse
    {
        $organization = $this->current->get();
        Gate::authorize('update', $organization);

        $this->profiles->removeLogo($organization);

        return back()->with('toast', __('profile.logo_removed'));
    }
}
