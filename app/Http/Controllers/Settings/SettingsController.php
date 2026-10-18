<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan: Profil usaha, Akun, Pemesanan (F-001 F3). F-007 adds more.
 */
class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'copy' => __('profile.settings'),
            'items' => [
                ['key' => 'profile', 'url' => route('settings.profile')],
                ['key' => 'account', 'url' => route('settings.account')],
                ['key' => 'booking', 'url' => route('settings.booking.edit')],
            ],
            'urls' => ['logout' => route('logout')],
            'logoutLabel' => __('account.settings.logout'),
        ]);
    }
}
