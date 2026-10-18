<?php

namespace App\Onboarding;

use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PaymentMethod;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Organizations\BookingSlug;
use App\Payments\PaymentMethodService;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use App\Trips\DepartureService;
use App\Trips\PublishChecklist;
use App\Trips\TripService;
use App\Trips\TripType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The guided first trip (F-001 B–E). Trips and departures are written through
 * F-003's services so the rules are not forked.
 */
final class OnboardingService
{
    public function __construct(
        private CurrentOrganization $current,
        private AuditLog $audit,
        private TripService $trips,
        private DepartureService $departures,
        private PaymentMethodService $paymentMethods,
        private PublishChecklist $checklist,
        private OnboardingProgress $progress,
    ) {}

    /**
     * Step 1 (B8–B11). Creates the organization and the owner membership in
     * one transaction, or updates the existing one (a retry or a step back).
     *
     * @param  array{name: string, city: string, whatsapp: string, slug: string}  $data  normalized and validated
     */
    public function saveOrganization(User $user, array $data): Organization
    {
        try {
            $organization = DB::transaction(function () use ($user, $data) {
                // B9: serialise this user's step-1 submits; a second one sees the first's organization.
                User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                $membership = ResolveCurrentOrganization::membershipOf($user);

                if ($membership !== null) {
                    return $this->updateOrganization($membership->organization, $user, $data);
                }

                $organization = new Organization($data);
                $organization->save();

                $this->current->runAs($organization, function () use ($organization, $user) {
                    OrganizationMember::create(['user_id' => $user->getKey(), 'role' => OrganizationMember::ROLE_OWNER]);

                    $this->audit->record(AuditAction::OrganizationCreated, $organization, after: [
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                        'city' => $organization->city,
                        'plan' => $organization->plan,
                    ]);
                });

                return $organization;
            });
        } catch (UniqueConstraintViolationException) {
            // B10: another seller took the slug between the check and the insert.
            throw ValidationException::withMessages(['slug' => __('onboarding.organization.slug_taken')]);
        }

        $this->current->set($organization);

        return $organization;
    }

    /**
     * Step 2 (C2–C4): the onboarding trip as a draft.
     *
     * @param  array<string, mixed>  $fields  normalized and validated trip fields
     */
    public function saveTrip(array $fields): Trip
    {
        $type = TripType::from($fields['type']);
        // C2: the type decides the booking mode.
        $fields['booking_mode'] = $type->preset()['booking_mode']->value;

        $trip = $this->progress->trip();

        if ($trip === null) {
            return $this->trips->create($fields);
        }

        $this->trips->update($trip, $fields);

        return $trip->refresh();
    }

    /**
     * Step 3 (C5–C8): the base price and, for scheduled trips, the first departure.
     *
     * @param  array{base_price: int, start_date?: string, capacity?: int, booking_deadline_preset?: string, booking_deadline_at?: ?string}  $data
     */
    public function saveSchedule(Trip $trip, array $data): void
    {
        DB::transaction(function () use ($trip, $data) {
            $this->trips->update($trip, ['base_price' => $data['base_price']]);

            if (! $trip->isScheduled()) {
                return;
            }

            $input = [
                'start_date' => $data['start_date'],
                'capacity' => $data['capacity'],
                'booking_deadline_preset' => $data['booking_deadline_preset'],
                'booking_deadline_at' => $data['booking_deadline_at'] ?? null,
            ];

            $departure = $this->departure($trip);

            if ($departure === null) {
                $this->departures->create($trip, $input);
            } else {
                $this->departures->update($departure, $input);
            }
        });
    }

    /**
     * The departure step 3 edits: the trip's first one that has not started.
     * A stale draft whose date has passed gets a new departure (E4); the
     * past one stays read-only (F-003).
     */
    public function departure(Trip $trip): ?TripDeparture
    {
        $trip->loadMissing('organization');

        return $trip->departures()
            ->whereDate('start_date', '>=', TripDeparture::today($trip->organization))
            ->orderBy('id')
            ->first();
    }

    /**
     * Step 4 "Simpan sebagai draf" (C12, E3): keeps the valid accounts and
     * ends the guided flow; the trip stays a draft.
     *
     * @param  list<array<string, mixed>>  $methods  validated blocks
     */
    public function saveDraft(Organization $organization, array $methods): void
    {
        DB::transaction(function () use ($organization, $methods) {
            $this->paymentMethods->sync($methods);

            $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $locked->onboarding_skipped_at ??= now();
            $locked->save();
            $organization->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Step 4 "Terbitkan trip" (D1–D3). Saves the accounts first; the publish
     * itself, the first-publish time and the audit event share one
     * transaction (TripService::publish), and a second tap publishes nothing new.
     *
     * @param  list<array<string, mixed>>  $methods  validated blocks
     * @return list<string> missing checklist items; empty when published
     */
    public function publish(Organization $organization, Trip $trip, array $methods): array
    {
        $this->paymentMethods->sync($methods);

        $trip->setRelation('organization', $organization);
        $missing = $trip->isPublished() ? [] : $this->checklist->missing($trip);

        if ($missing !== []) {
            return $missing;
        }

        // Sets first_published_at in the same transaction (TripService, D2).
        $this->trips->publish($trip);
        $organization->refresh();

        return [];
    }

    /**
     * D1: the step that fixes a missing checklist item.
     */
    public static function stepFor(string $item): int
    {
        return match ($item) {
            PublishChecklist::COVER_PHOTO, PublishChecklist::NAME => 2,
            PublishChecklist::BASE_PRICE, PublishChecklist::DEPARTURE => 3,
            default => 4,
        };
    }

    /**
     * B11: step 1 again before the first publish.
     *
     * @param  array{name: string, city: string, whatsapp: string, slug: string}  $data
     */
    private function updateOrganization(Organization $organization, User $user, array $data): Organization
    {
        $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->slugLocked() && $data['slug'] !== $locked->slug) {
            throw ValidationException::withMessages(['slug' => __('onboarding.organization.slug_locked')]);
        }

        $locked->fill($data);
        $slugChanged = $locked->isDirty('slug');
        $locked->save();

        if ($slugChanged) {
            $this->current->runAs($locked, fn () => $this->audit->record(
                AuditAction::OrganizationSlugChanged,
                $locked,
                ['slug' => $locked->getPrevious()['slug'] ?? null],
                ['slug' => $locked->slug],
            ));
        }

        return $locked;
    }

    /**
     * B6 helper for the controller: the slug state with suggestions.
     *
     * @return array{state: string, suggestions: list<string>}
     */
    public function slugCheck(string $slug, ?string $name, ?string $city): array
    {
        $slug = BookingSlug::normalize($slug);
        $state = BookingSlug::state($slug, $this->current->get());

        return [
            'state' => $state,
            'suggestions' => $state === BookingSlug::TAKEN || $state === BookingSlug::RESERVED
                ? BookingSlug::suggestions($slug, $name, $city, $this->current->get())
                : [],
        ];
    }

    /**
     * Saved payment methods in display order (step 4).
     *
     * @return list<array<string, mixed>>
     */
    public function paymentMethods(): array
    {
        return PaymentMethod::query()->orderBy('position')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->public_id,
                'provider' => $method->provider,
                'provider_name' => $method->provider_name,
                'account_number' => $method->account_number,
                'account_holder' => $method->account_holder,
            ])->all();
    }
}
