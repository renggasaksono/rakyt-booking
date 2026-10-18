<?php

use App\Http\Controllers\Trips\BookingSettingsController;
use App\Http\Controllers\Trips\TripController;
use App\Http\Controllers\Trips\TripDepartureController;
use App\Http\Controllers\Trips\TripPhotoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'tagline' => __('home.tagline'),
]))->name('home');

/*
| Seller app (F-003). Every first segment here is on F-001's reserved-slug list.
| Sign-in arrives with F-001; these routes are covered by feature tests (F-003 Q10).
*/
Route::middleware(['auth', 'organization'])->group(function () {
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
