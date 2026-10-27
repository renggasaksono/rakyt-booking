<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSellerController;
use App\Http\Controllers\Admin\AdminSellerStatusController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Bookings\SellerBookingController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\PublicPages\BookingController;
use App\Http\Controllers\PublicPages\PublicPageController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\PaymentMethodController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Trips\BookingSettingsController;
use App\Http\Controllers\Trips\TripController;
use App\Http\Controllers\Trips\TripDepartureController;
use App\Http\Controllers\Trips\TripPhotoController;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public marketing landing page (F-017). Not a `/<slug>` fallback — it resolves
// no tenant; head tags are server-rendered for link previews (ADR-0024).
Route::get('/', HomeController::class)->name('home');

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

// Shared with the platform admin: the TOTP challenge and the admin shell sign out here.
Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
| Seller-only, like every `auth` route outside /admin: the platform admin is
| not a tenant (F-010, ADR-0011), so `not-platform-admin` turns them away.
*/
Route::middleware(['auth', 'not-platform-admin'])->group(function () {
    Route::post('/verifikasi/kirim-ulang', [EmailVerificationController::class, 'send'])->middleware('throttle:verification-resend')->name('verification.send');

    Route::get('/pengaturan/akun', [AccountController::class, 'edit'])->name('settings.account');
    Route::put('/pengaturan/akun/nama', [AccountController::class, 'updateName'])->middleware('throttle:account-change')->name('settings.account.name');
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
| Platform admin (F-010, ADR-0011). A module of this app, not a tenant: no
| `organization`/`onboarded`. Every route sits behind `auth` plus a server-side
| gate; a seller, whatever URL they type, is forbidden. `admin` is a reserved
| slug, so these never collide with a seller's booking link.
|
| The TOTP challenge sits behind `platform-admin.pending` (admin, not yet
| challenged); everything else behind `platform-admin` (admin, past TOTP, inside
| the shorter admin session window).
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['auth', 'platform-admin.pending'])->group(function () {
        Route::get('/dua-faktor', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('/dua-faktor', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:admin-2fa')
            ->name('two-factor.verify');
    });

    Route::middleware(['auth', 'platform-admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        // F-015: read-only — audit events are append-only, so no write routes.
        Route::get('/audit', [AdminAuditController::class, 'index'])->name('audit.index');

        // F-012: seller directory. `cari` before `{organization}`.
        Route::get('/penjual', [AdminSellerController::class, 'index'])->name('sellers.index');
        Route::get('/penjual/cari', [AdminSellerController::class, 'lookup'])->name('sellers.lookup');
        Route::get('/penjual/{organization}', [AdminSellerController::class, 'show'])->name('sellers.show');
        // AC-7: payment evidence only through a short-lived signed URL issued to the admin.
        Route::get('/penjual/{organization}/pesanan/{booking}/bukti', [AdminSellerController::class, 'evidence'])
            ->name('sellers.evidence');
        Route::get('/penjual/{organization}/pesanan/{booking}/bukti/lihat', [AdminSellerController::class, 'showEvidence'])
            ->middleware(['signed', 'no-referrer'])
            ->name('sellers.evidence.show');
        // F-014: the only admin writes on a seller — its account status, with a reason, audited.
        Route::post('/penjual/{organization}/tangguhkan', [AdminSellerStatusController::class, 'suspend'])
            ->name('sellers.suspend');
        Route::post('/penjual/{organization}/aktifkan', [AdminSellerStatusController::class, 'reactivate'])
            ->name('sellers.reactivate');
    });
});

/*
| Seller app. A seller without an organization goes to onboarding step 1
| (E1); one still in the guided flow resumes it (E2). The platform admin is
| turned away first (F-010).
*/
Route::middleware(['auth', 'not-platform-admin', 'organization', 'onboarded'])->group(function () {
    // "Hari ini" (F-007 AC-1): sellers land here after sign-in and onboarding.
    Route::get('/hari-ini', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', fn () => redirect()->route('dashboard'));

    // The seller's bookings (F-007 sections C–E). `pesanan` is a reserved slug.
    Route::get('/pesanan', [SellerBookingController::class, 'index'])->name('bookings.index');
    Route::get('/pesanan/{booking}', [SellerBookingController::class, 'show'])->name('bookings.show');
    Route::post('/pesanan/{booking}/batalkan', [SellerBookingController::class, 'cancel'])->name('bookings.cancel');
    // F-006: the seller verifies a payment inline (AC-16–AC-18) and views the gated evidence (AC-19).
    Route::post('/pesanan/{booking}/konfirmasi', [SellerBookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/pesanan/{booking}/tolak', [SellerBookingController::class, 'reject'])->name('bookings.reject');
    Route::get('/pesanan/{booking}/bukti', [SellerBookingController::class, 'evidence'])->middleware('no-referrer')->name('bookings.evidence');
    // Hari ini's "Ingatkan via WA": redirects to WhatsApp so the token stays out of its props (ADR-0029 §1).
    Route::get('/pesanan/{booking}/ingatkan', [SellerBookingController::class, 'remind'])->middleware('no-referrer')->name('bookings.remind');

    Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/pengaturan/profil', [ProfileController::class, 'edit'])->name('settings.profile');
    Route::put('/pengaturan/profil', [ProfileController::class, 'update']);
    Route::post('/pengaturan/profil/logo', [ProfileController::class, 'storeLogo'])->name('settings.profile.logo');
    Route::delete('/pengaturan/profil/logo', [ProfileController::class, 'destroyLogo']);
    // Payment instructions: bank / e-wallet + QRIS (F-006, supersedes F-001's Rekening).
    // The trip editor's checklist links here. POST carries the QRIS image (multipart).
    Route::get('/pengaturan/pembayaran', [PaymentMethodController::class, 'index'])->name('settings.payment');
    Route::post('/pengaturan/pembayaran', [PaymentMethodController::class, 'update'])->middleware('throttle:payment-change')->name('settings.payment.update');
    Route::get('/pengaturan/pembayaran/qris', [PaymentMethodController::class, 'qris'])->middleware('no-referrer')->name('settings.payment.qris');

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

/*
| Customer booking (F-005, ADR-0027): the form, its submit and the customer's
| booking page. Fallback routes like the public pages below, so they never
| shadow an app route; the organization comes from the slug.
|
| Unlike the public pages they run the session, its errors and CSRF (the form
| is an Inertia POST, ADR-0027 §3), but never resolve the signed-in seller:
| nothing of a seller's session is read here (AC-38 d). Session payloads are
| encrypted (SESSION_ENCRYPT), since the booking page's URL carries its token.
*/
Route::middleware('public-organization')->withoutMiddleware([
    AuthenticateSession::class,
    ResolveCurrentOrganization::class,
])->group(function () {
    // The token pattern keeps `/<slug>/pesanan/pesan` (a trip called "pesanan") reaching the form.
    // Runs the session like the form (CSRF cookie and flashed messages for F-006's upload);
    // the session payload is encrypted, so the stored URL does not expose the token (ADR-0028 §1).
    Route::get('/{organization}/pesanan/{token}', [BookingController::class, 'show'])
        ->where(['organization' => '[^/]+', 'token' => '[A-Za-z0-9]{32}'])
        ->middleware(['throttle:public-page', 'no-referrer'])
        ->fallback()
        ->name('public.booking.show');
    // F-006: the customer uploads payment evidence (AC-12) and views their own evidence (AC-19).
    // throttle:evidence-upload keys off the {token} segment (F-009 R1).
    Route::post('/{organization}/pesanan/{token}/bukti', [BookingController::class, 'uploadEvidence'])
        ->where(['organization' => '[^/]+', 'token' => '[A-Za-z0-9]{32}'])
        ->middleware(['throttle:evidence-upload', 'no-referrer'])
        ->fallback()
        ->name('public.booking.evidence');
    Route::get('/{organization}/pesanan/{token}/bukti', [BookingController::class, 'showEvidence'])
        ->where(['organization' => '[^/]+', 'token' => '[A-Za-z0-9]{32}'])
        ->middleware(['throttle:public-page', 'no-referrer'])
        ->fallback()
        ->name('public.booking.evidence.show');
    // F-006 AC-22: the seller's active QRIS image, gated by the booking token.
    Route::get('/{organization}/pesanan/{token}/qris', [BookingController::class, 'showQris'])
        ->where(['organization' => '[^/]+', 'token' => '[A-Za-z0-9]{32}'])
        ->middleware(['throttle:public-page', 'no-referrer'])
        ->fallback()
        ->name('public.booking.qris');
    Route::get('/{organization}/{trip}/pesan', [BookingController::class, 'create'])
        ->where(['organization' => '[^/]+', 'trip' => '[^/]+'])
        ->middleware('throttle:public-page')
        ->fallback()
        ->name('public.booking.create');
    Route::post('/{organization}/{trip}/pesan', [BookingController::class, 'store'])
        ->where(['organization' => '[^/]+', 'trip' => '[^/]+'])
        ->middleware('throttle:public-booking')
        ->fallback()
        ->name('public.booking.store');
});

/*
| Public booking pages rakyt.id/<slug>[/<trip>] (F-002). Fallback routes:
| matched only after every other route, so they never shadow an app route
| (every app route's first segment is also a reserved slug). The patterns
| accept any segment so unknown, reserved and malformed slugs end in the same
| 404 from ResolvePublicOrganization (AC-3). Not `auth`, `organization` or
| `onboarded`: the organization comes from the slug (AC-7).
|
| No session (ADR-0025 amendment): customers, crawlers and link-preview bots send
| no cookie, so a session would write a row per hit; nothing on these pages
| needs one, and without it nothing of a signed-in seller can reach them.
*/
Route::middleware(['throttle:public-page', 'public-organization'])->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    AuthenticateSession::class,
    ResolveCurrentOrganization::class,
])->group(function () {
    Route::get('/{organization}', [PublicPageController::class, 'seller'])
        ->where('organization', '[^/]+')
        ->fallback()
        ->name('public.seller');
    Route::get('/{organization}/{trip}', [PublicPageController::class, 'trip'])
        ->where(['organization' => '[^/]+', 'trip' => '[^/]+'])
        ->fallback()
        ->name('public.trip');
});
