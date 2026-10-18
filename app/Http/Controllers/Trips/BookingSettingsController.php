<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentOrganization;
use App\Trips\DeadlinePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Pemesanan: the organization's default booking deadline (F-003
 * Build order, Q6). The rest of Pengaturan is F-001.
 */
class BookingSettingsController extends Controller
{
    public function __construct(private CurrentOrganization $current) {}

    public function edit(): Response
    {
        $organization = $this->current->get();
        Gate::authorize('view', $organization);

        return Inertia::render('Settings/Booking', [
            'settings' => ['default_booking_deadline_preset' => $organization->default_booking_deadline_preset],
            'options' => array_map(fn (DeadlinePreset $preset) => $preset->value, DeadlinePreset::organizationDefaults()),
            'urls' => ['update' => route('settings.booking.update'), 'back' => route('settings.index')],
            'backLabel' => __('profile.settings.back'),
            'copy' => __('trips'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->current->get();
        Gate::authorize('update', $organization);

        $data = $request->validate([
            'default_booking_deadline_preset' => ['required', Rule::in(array_map(fn (DeadlinePreset $preset) => $preset->value, DeadlinePreset::organizationDefaults()))],
        ], attributes: __('trips.attributes'));

        $organization->default_booking_deadline_preset = $data['default_booking_deadline_preset'];
        $organization->save();

        return back()->with('toast', __('trips.settings.saved'));
    }
}
