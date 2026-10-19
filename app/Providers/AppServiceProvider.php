<?php

namespace App\Providers;

use App\Bookings\BookingDepartureBookings;
use App\Payments\ActivePaymentMethods;
use App\Security\RateLimits;
use App\Tenancy\CurrentOrganization;
use App\Trips\Contracts\DepartureBookings;
use App\Trips\Contracts\PaymentReadiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Reset per request / per queued job (ADR-0004).
        $this->app->scoped(CurrentOrganization::class);

        // F-003 seams: the real payment-method check (F-001, F-003 Q9) and
        // the real bookings query (F-005 AC-41).
        $this->app->bind(PaymentReadiness::class, ActivePaymentMethods::class);
        $this->app->bind(DepartureBookings::class, BookingDepartureBookings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimits::register();
    }
}
