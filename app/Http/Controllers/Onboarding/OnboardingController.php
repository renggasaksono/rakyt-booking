<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Trip;
use App\Onboarding\OnboardingProgress;
use App\Onboarding\OnboardingService;
use App\Organizations\BookingSlug;
use App\Organizations\OrganizationRules;
use App\Payments\PaymentMethodRules;
use App\Tenancy\CurrentOrganization;
use App\Trips\BookingMode;
use App\Trips\DeadlinePreset;
use App\Trips\PublishChecklist;
use App\Trips\TripPresenter;
use App\Trips\TripPreview;
use App\Trips\TripRules;
use App\Trips\TripType;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guided first trip, steps 1–4 and the published screen (mockups 4b–4f,
 * F-001 B–E). The organization always comes from the signed-in user's
 * membership (T1); trips and departures go through F-003's services.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private CurrentOrganization $current,
        private OnboardingService $onboarding,
        private OnboardingProgress $progress,
        private TripPresenter $presenter,
    ) {}

    /**
     * E1–E3: where the seller should be.
     */
    public function resume(Request $request): RedirectResponse
    {
        return redirect()->to($this->progress->landingUrl($request->user()));
    }

    public function organization(Request $request): Response|RedirectResponse
    {
        $organization = $this->current->get();

        if ($redirect = $this->leaveIfFinished($organization)) {
            return $redirect;
        }

        return $this->step(1, 'Onboarding/Organization', [
            'organization' => $organization === null ? null : [
                'name' => $organization->name,
                'city' => $organization->city,
                'whatsapp' => self::nationalNumber($organization->whatsapp),
                'slug' => $organization->slug,
            ],
            'slugLocked' => $organization?->slugLocked() ?? false,
            'publicHost' => config('rakyt.public_host'),
            'limits' => ['name' => OrganizationRules::NAME_MAX, 'city' => OrganizationRules::CITY_MAX, 'slug' => BookingSlug::MAX],
            'urls' => [
                'store' => route('onboarding.organization'),
                'slug' => route('onboarding.slug'),
            ],
        ]);
    }

    /**
     * B8–B11.
     */
    public function storeOrganization(Request $request): RedirectResponse
    {
        if ($redirect = $this->leaveIfFinished($this->current->get())) {
            return $redirect;
        }

        $input = OrganizationRules::normalize($request->only(['name', 'city', 'whatsapp', 'slug']));
        $data = Validator::make($input, OrganizationRules::rules($this->current->get()), OrganizationRules::messages(), OrganizationRules::attributes())->validate();

        $this->onboarding->saveOrganization($request->user(), $data);

        return redirect()->route('onboarding.trip');
    }

    /**
     * B6, B12: the state only, never anything about the owner of a slug.
     */
    public function slug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json($this->onboarding->slugCheck(
            (string) $request->query('slug'),
            $request->query('name'),
            $request->query('city'),
        ));
    }

    public function trip(): Response|RedirectResponse
    {
        if ($redirect = $this->guard(2)) {
            return $redirect;
        }

        $trip = $this->progress->trip();
        $trip?->setRelation('organization', $this->current->get());
        $options = $this->presenter->options();

        return $this->step(2, 'Onboarding/Trip', [
            'trip' => $trip === null ? null : [
                'type' => $trip->type->value,
                'name' => $trip->name,
                'duration_label' => $trip->duration_label,
                'duration_days' => $trip->duration_days,
                'duration_nights' => $trip->duration_nights,
                'inclusions' => $trip->inclusions ?? [],
                'photos' => $this->presenter->photos($trip),
            ],
            'options' => [
                ...$options,
                // Q7: a generic set in onboarding.
                'inclusion_suggestions' => __('onboarding.trip.inclusion_suggestions'),
            ],
            'tripCopy' => __('trips'),
            'urls' => [
                'store' => route('onboarding.trip'),
                'back' => route('onboarding.organization'),
                'next' => route('onboarding.schedule'),
                'photos' => $trip ? route('trips.photos.store', $trip) : null,
                'photos_reorder' => $trip ? route('trips.photos.reorder', $trip) : null,
            ],
        ]);
    }

    /**
     * C2–C4. JSON for the page (photos picked before the trip existed upload
     * next), a redirect otherwise.
     */
    public function storeTrip(Request $request): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->guard(2)) {
            return $redirect;
        }

        Gate::authorize('create', Trip::class);

        $input = TripRules::normalize($request->only(['type', 'name', 'duration_label', 'duration_days', 'duration_nights', 'inclusions']));
        $rules = TripRules::rules();

        $fields = Validator::make($input, [
            'type' => ['required', Rule::enum(TripType::class)],
            'name' => $rules['name'],
            'duration_label' => ['required', Rule::in(['hours', 'days'])],
            'duration_days' => $rules['duration_days'],
            'duration_nights' => $rules['duration_nights'],
            'inclusions' => $rules['inclusions'],
            'inclusions.*' => $rules['inclusions.*'],
        ], [
            ...TripRules::messages(),
            'type.required' => __('onboarding.trip.type_required'),
            'duration_label.required' => __('onboarding.trip.duration_required'),
        ], __('trips.attributes'))->validate();

        foreach (['duration_days', 'duration_nights'] as $key) {
            $fields[$key] = (int) $fields[$key];
        }

        $trip = $this->onboarding->saveTrip($fields);

        if ($request->expectsJson()) {
            return response()->json([
                'urls' => [
                    'photos' => route('trips.photos.store', $trip),
                    'photos_reorder' => route('trips.photos.reorder', $trip),
                    'next' => route('onboarding.schedule'),
                ],
            ]);
        }

        return redirect()->route('onboarding.schedule');
    }

    public function schedule(): Response|RedirectResponse
    {
        if ($redirect = $this->guard(3)) {
            return $redirect;
        }

        $organization = $this->current->get();
        $trip = $this->progress->trip();
        $trip->setRelation('organization', $organization);
        $departure = $this->onboarding->departure($trip);

        return $this->step(3, 'Onboarding/Schedule', [
            'trip' => [
                'booking_mode' => $trip->booking_mode->value,
                'base_price' => $trip->base_price,
                'duration_days' => $trip->duration_days,
                'duration_display' => $trip->duration()?->display(),
                'meeting_time' => $trip->meeting_time,
            ],
            'departure' => $departure === null ? null : [
                'start_date' => $departure->start_date->toDateString(),
                'capacity' => $departure->capacity,
                'booking_deadline_preset' => $departure->booking_deadline_preset->value,
                'booking_deadline_local' => $departure->booking_deadline_preset === DeadlinePreset::Custom
                    ? $departure->booking_deadline_at->setTimezone($organization->timezone)->format('Y-m-d H:i')
                    : null,
            ],
            'context' => $this->presenter->context($organization),
            'options' => [
                'deadline_presets' => array_map(fn (DeadlinePreset $preset) => $preset->value, DeadlinePreset::cases()),
                'limits' => [
                    'price_min' => TripRules::PRICE_MIN,
                    'price_max' => TripRules::PRICE_MAX,
                    'seats_max' => (int) config('rakyt.onboarding.max_seats'),
                ],
            ],
            'tripCopy' => __('trips'),
            'urls' => [
                'store' => route('onboarding.schedule'),
                'back' => route('onboarding.trip'),
            ],
        ]);
    }

    /**
     * C5–C8.
     */
    public function storeSchedule(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(3)) {
            return $redirect;
        }

        $trip = $this->progress->trip();
        Gate::authorize('update', $trip);

        $rules = [
            'base_price' => ['required', 'integer', 'between:'.TripRules::PRICE_MIN.','.TripRules::PRICE_MAX],
        ];

        if ($trip->booking_mode === BookingMode::Scheduled) {
            $rules += [
                'start_date' => ['required', 'date_format:Y-m-d'],
                'capacity' => ['required', 'integer', 'between:1,'.config('rakyt.onboarding.max_seats')],
                'booking_deadline_preset' => ['required', Rule::enum(DeadlinePreset::class)],
                'booking_deadline_at' => ['nullable', 'string', 'max:20', 'required_if:booking_deadline_preset,custom'],
            ];
        }

        $data = $request->validate($rules, [
            'base_price.between' => __('trips.errors.price_range'),
            'base_price.integer' => __('trips.errors.price_range'),
            'capacity.between' => __('onboarding.schedule.seats_range', ['max' => config('rakyt.onboarding.max_seats')]),
            'capacity.integer' => __('onboarding.schedule.seats_range', ['max' => config('rakyt.onboarding.max_seats')]),
            'booking_deadline_at.required_if' => __('trips.errors.deadline_custom_required'),
        ], [...__('trips.attributes'), 'base_price' => __('onboarding.schedule.price'), 'capacity' => __('onboarding.schedule.seats')]);

        foreach (['base_price', 'capacity'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }

        $this->onboarding->saveSchedule($trip, $data);

        return redirect()->route('onboarding.payment');
    }

    public function payment(): Response|RedirectResponse
    {
        if ($redirect = $this->guard(4)) {
            return $redirect;
        }

        $providers = [];

        foreach (config('rakyt.payment_providers') as $key => $provider) {
            $providers[] = ['value' => $key, 'label' => $provider['label'], 'type' => $provider['type'], 'asks_name' => $provider['name'] ?? false];
        }

        return $this->step(4, 'Onboarding/Payment', [
            'methods' => $this->onboarding->paymentMethods(),
            'providers' => $providers,
            'limits' => [
                'methods' => (int) config('rakyt.onboarding.max_payment_methods'),
                'holder' => PaymentMethodRules::HOLDER_MAX,
                'provider_name' => PaymentMethodRules::PROVIDER_NAME_MAX,
            ],
            'urls' => [
                'store' => route('onboarding.payment'),
                'back' => route('onboarding.schedule'),
            ],
        ]);
    }

    /**
     * C10–C12, D1–D3.
     */
    public function storePayment(Request $request): RedirectResponse
    {
        if ($redirect = $this->guard(4)) {
            return $redirect;
        }

        $organization = $this->current->get();
        $trip = $this->progress->trip();
        Gate::authorize('update', $trip);

        $methods = PaymentMethodRules::normalize($request->input('methods', []));

        if ($request->input('intent') === 'draft') {
            $this->onboarding->saveDraft($organization, $this->validBlocks($methods));

            return redirect()->route('dashboard')->with('toast', __('onboarding.payment.draft_saved'));
        }

        $validator = Validator::make(['methods' => $methods], PaymentMethodRules::rules(requireOne: true), PaymentMethodRules::messages(), PaymentMethodRules::attributes());
        $validator->after(function ($validator) use ($methods) {
            foreach (is_array($methods) ? PaymentMethodRules::missingProviderNames($methods) : [] as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        });
        $data = $validator->validate();

        $missing = $this->onboarding->publish($organization, $trip, $data['methods']);

        if ($missing !== []) {
            return $this->toFirstFailingStep($trip, $missing);
        }

        return redirect()->route('onboarding.done');
    }

    /**
     * 4f (D4–D6).
     */
    public function done(Request $request): Response|RedirectResponse
    {
        $organization = $this->current->get();

        if ($organization === null || $organization->first_published_at === null) {
            return redirect()->to($this->progress->landingUrl($request->user()));
        }

        $trip = Trip::query()->where('status', 'published')->orderBy('published_at')->orderBy('id')->first()
            ?? $this->progress->trip();
        $trip->setRelation('organization', $organization);
        $preview = TripPreview::for($trip);

        return Inertia::render('Onboarding/Done', [
            'copy' => __('onboarding'),
            'elapsed' => $this->elapsed($request->user()->signup_started_at, $organization->first_published_at),
            'trip' => [
                'name' => $trip->name,
                'cover_url' => $preview['cover_url'],
                'from_price' => $preview['from_price'],
                'request_date' => $trip->booking_mode === BookingMode::RequestDate,
                'next_departure' => $preview['next_departure'],
            ],
            'link' => [
                'display' => BookingSlug::display($organization->slug),
                'url' => BookingSlug::url($organization->slug),
            ],
            'calendar' => __('trips.calendar'),
            'urls' => [
                'dashboard' => route('dashboard'),
                'trip' => route('trips.edit', $trip),
                'profile' => route('settings.profile'),
            ],
        ]);
    }

    /**
     * D4: shown only when the first publish came within the configured window (Q5).
     *
     * @return array{minutes: int, seconds: int}|null
     */
    private function elapsed(?CarbonInterface $start, ?CarbonInterface $published): ?array
    {
        if ($start === null || $published === null || $published->lessThan($start)) {
            return null;
        }

        $seconds = (int) $start->diffInSeconds($published);

        if ($seconds > (int) config('rakyt.onboarding.show_elapsed_up_to_minutes') * 60) {
            return null;
        }

        return ['minutes' => intdiv($seconds, 60), 'seconds' => $seconds % 60];
    }

    /**
     * C12: a draft keeps the blocks that are complete and valid.
     *
     * @return list<array<string, mixed>>
     */
    private function validBlocks(mixed $methods): array
    {
        if (! is_array($methods)) {
            return [];
        }

        $valid = [];

        foreach (array_slice($methods, 0, (int) config('rakyt.onboarding.max_payment_methods')) as $block) {
            $check = Validator::make(['methods' => [$block]], PaymentMethodRules::rules(requireOne: true));

            if (is_array($block) && ! $check->fails() && PaymentMethodRules::missingProviderNames([$block]) === []) {
                $valid[] = $check->validated()['methods'][0];
            }
        }

        return $valid;
    }

    /**
     * D1, E4: back to the first step with a missing item, highlighted.
     *
     * @param  list<string>  $missing
     */
    private function toFirstFailingStep(Trip $trip, array $missing): RedirectResponse
    {
        usort($missing, fn (string $a, string $b) => OnboardingService::stepFor($a) <=> OnboardingService::stepFor($b));
        $item = $missing[0];
        $step = OnboardingService::stepFor($item);

        $message = $item === PublishChecklist::DEPARTURE && $trip->departures()->exists()
            ? __('onboarding.schedule.start_date_past')
            : __("onboarding.publish.missing.{$item}");

        return redirect()->route(OnboardingProgress::STEPS[$step])
            ->with('highlight', $item)
            ->withErrors(['publish' => $message]);
    }

    /**
     * Steps 2–4 need every earlier step saved; finished sellers go to the dashboard.
     */
    private function guard(int $step): ?RedirectResponse
    {
        $organization = $this->current->get();

        if ($redirect = $this->leaveIfFinished($organization)) {
            return $redirect;
        }

        $furthest = $this->progress->furthestStep($organization);

        return $step > $furthest ? redirect()->route(OnboardingProgress::STEPS[$furthest]) : null;
    }

    private function leaveIfFinished(?Organization $organization): ?RedirectResponse
    {
        return $organization !== null && ! $organization->isOnboarding() ? redirect()->route('dashboard') : null;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function step(int $step, string $component, array $props): Response
    {
        return Inertia::render($component, [
            ...$props,
            'header' => [
                'step' => $step,
                'name' => __("onboarding.steps.{$step}"),
                'minutes_left' => (int) config("rakyt.onboarding.minutes_left.{$step}"),
            ],
            'highlight' => session('highlight'),
            'copy' => __('onboarding'),
        ]);
    }

    /**
     * "+6281277881020" → "81277881020" for the field after the fixed "+62".
     */
    private static function nationalNumber(?string $e164): ?string
    {
        return $e164 !== null && str_starts_with($e164, '+62') ? substr($e164, 3) : $e164;
    }
}
