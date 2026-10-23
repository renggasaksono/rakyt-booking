<?php

namespace App\Onboarding;

use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Trip;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use App\Tenancy\Middleware\ResolveCurrentOrganization;

/**
 * Where a seller resumes (F-001 E1–E3), derived from data only: no
 * organization → step 1; no trip → 2; a scheduled trip without a departure
 * or a trip without a price → 3; no payment method → 4; otherwise 4.
 */
final class OnboardingProgress
{
    public const STEPS = [
        1 => 'onboarding.organization',
        2 => 'onboarding.trip',
        3 => 'onboarding.schedule',
        4 => 'onboarding.payment',
    ];

    public function __construct(private CurrentOrganization $current) {}

    /**
     * After sign-up, sign-in or any visit to a seller page.
     */
    public function landingUrl(User $user): string
    {
        // F-010: a platform admin has no organization; they land in the admin
        // area, where the gate then requires the TOTP challenge.
        if ($user->is_platform_admin) {
            return route('admin.dashboard');
        }

        $organization = $this->current->get() ?? ResolveCurrentOrganization::membershipOf($user)?->organization;

        if ($organization === null) {
            return route(self::STEPS[1]);
        }

        if ($organization->isOnboarding()) {
            return route(self::STEPS[$this->resumeStep($organization)]);
        }

        return route('dashboard');
    }

    public function resumeStep(?Organization $organization): int
    {
        if ($organization === null) {
            return 1;
        }

        return $this->current->runAs($organization, function () {
            $trip = $this->trip();

            return match (true) {
                $trip === null => 2,
                $trip->base_price === null || ($trip->isScheduled() && ! $trip->departures()->exists()) => 3,
                ! PaymentMethod::query()->exists() => 4,
                default => 4,
            };
        });
    }

    /**
     * The trip made in onboarding: the organization's first trip.
     * Call with the organization as the current one.
     */
    public function trip(): ?Trip
    {
        return Trip::query()->orderBy('id')->first();
    }

    /**
     * The highest step the seller may open: every earlier step is saved.
     */
    public function furthestStep(?Organization $organization): int
    {
        return $this->resumeStep($organization);
    }
}
