<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Trips\BookingSettingsController;
use App\Http\Controllers\Trips\TripController;
use App\Http\Controllers\Trips\TripDepartureController;
use App\Http\Controllers\Trips\TripPhotoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'tagline' => __('home.tagline'),
    'links' => [
        'register' => ['label' => __('account.register.submit'), 'url' => route('register')],
        'login' => ['label' => __('account.login.submit'), 'url' => route('login')],
    ],
]))->name('home');

/*
| Every first path segment below is on the reserved-slug list
| (config/rakyt.php → reserved_slugs, F-001 B7); a test checks it.
*/

Route::get('/syarat', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privasi', [LegalController::class, 'privacy'])->name('legal.privacy');

/*
| Seller account (F-001 section A, ADR-0012).
*/
Route::middleware('guest')->group(function () {
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:signup');
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:seller-login');
    Route::get('/lupa-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    // The token is in the query string so log redaction catches it (A13).
    Route::get('/password/baru', [NewPasswordController::class, 'create'])->middleware('no-referrer')->name('password.reset');
    Route::post('/password/baru', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.store');
});

// Signed and expiring; works without being signed in (A10).
Route::get('/verifikasi/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['no-referrer', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/verifikasi/kirim-ulang', [EmailVerificationController::class, 'send'])->middleware('throttle:verification-resend')->name('verification.send');

    Route::get('/pengaturan/akun', [AccountController::class, 'edit'])->name('settings.account');
    Route::put('/pengaturan/akun/email', [AccountController::class, 'updateEmail'])->middleware('throttle:account-change')->name('settings.account.email');
    Route::put('/pengaturan/akun/password', [AccountController::class, 'updatePassword'])->middleware('throttle:account-change')->name('settings.account.password');

    /*
    | Guided first trip, mockups 4b–4f (F-001 sections B–E).
    */
    Route::get('/mulai', [OnboardingController::class, 'resume'])->name('onboarding');
    Route::get('/mulai/usaha', [OnboardingController::class, 'organization'])->name('onboarding.organization');
    Route::post('/mulai/usaha', [OnboardingController::class, 'storeOrganization']);
    Route::get('/mulai/cek-link', [OnboardingController::class, 'slug'])->middleware('throttle:slug-check')->name('onboarding.slug');
    Route::get('/mulai/trip', [OnboardingController::class, 'trip'])->name('onboarding.trip');
    Route::post('/mulai/trip', [OnboardingController::class, 'storeTrip']);
    Route::get('/mulai/jadwal', [OnboardingController::class, 'schedule'])->name('onboarding.schedule');
    Route::post('/mulai/jadwal', [OnboardingController::class, 'storeSchedule']);
    Route::get('/mulai/pembayaran', [OnboardingController::class, 'payment'])->name('onboarding.payment');
    Route::post('/mulai/pembayaran', [OnboardingController::class, 'storePayment']);
    Route::get('/mulai/selesai', [OnboardingController::class, 'done'])->name('onboarding.done');
});

/*
| Seller app. A seller without an organization goes to onboarding step 1
| (E1); one still in the guided flow resumes it (E2).
*/
Route::middleware(['auth', 'organization', 'onboarded'])->group(function () {
    // F-007 builds the "Hari ini" dashboard; until then sellers land on their trips.
    Route::get('/dashboard', fn () => redirect()->route('trips.index'))->name('dashboard');

    Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/pengaturan/profil', [ProfileController::class, 'edit'])->name('settings.profile');
    Route::put('/pengaturan/profil', [ProfileController::class, 'update']);
    Route::post('/pengaturan/profil/logo', [ProfileController::class, 'storeLogo'])->name('settings.profile.logo');
    Route::delete('/pengaturan/profil/logo', [ProfileController::class, 'destroyLogo']);

    Route::get('/trip', [TripController::class, 'index'])->name('trips.index');
    Route::get('/trip/baru', [TripController::class, 'create'])->name('trips.create');
    Route::post('/trip', [TripController::class, 'store'])->name('trips.store');

    Route::prefix('/trip/{trip}')->scopeBindings()->group(function () {
        Route::get('/', [TripController::class, 'edit'])->name('trips.edit');
        Route::patch('/draf', [TripController::class, 'autosave'])->name('trips.autosave');
        Route::put('/', [TripController::class, 'update'])->name('trips.update');
        Route::post('/terbitkan', [TripController::class, 'publish'])->name('trips.publish');
        Route::post('/sembunyikan', [TripController::class, 'unpublish'])->name('trips.unpublish');
        Route::delete('/', [TripController::class, 'destroy'])->name('trips.destroy');

        Route::post('/foto', [TripPhotoController::class, 'store'])->name('trips.photos.store');
        Route::put('/foto/urutan', [TripPhotoController::class, 'reorder'])->name('trips.photos.reorder');
        Route::delete('/foto/{photo}', [TripPhotoController::class, 'destroy'])->name('trips.photos.destroy');

        Route::post('/keberangkatan', [TripDepartureController::class, 'store'])->name('trips.departures.store');
        Route::get('/keberangkatan/salin', [TripDepartureController::class, 'copyTemplate'])->name('trips.departures.copy');
        Route::put('/keberangkatan/{departure}', [TripDepartureController::class, 'update'])->name('trips.departures.update');
        Route::patch('/keberangkatan/{departure}/cepat', [TripDepartureController::class, 'quickUpdate'])->name('trips.departures.quick');
        Route::delete('/keberangkatan/{departure}', [TripDepartureController::class, 'destroy'])->name('trips.departures.destroy');
    });

    Route::get('/keberangkatan', [TripDepartureController::class, 'upcoming'])->name('departures.upcoming');

    Route::get('/pengaturan/pemesanan', [BookingSettingsController::class, 'edit'])->name('settings.booking.edit');
    Route::put('/pengaturan/pemesanan', [BookingSettingsController::class, 'update'])->name('settings.booking.update');
});
