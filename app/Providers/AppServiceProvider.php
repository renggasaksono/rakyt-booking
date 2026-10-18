<?php

namespace App\Providers;

use App\Payments\ActivePaymentMethods;
use App\Security\RateLimits;
use App\Tenancy\CurrentOrganization;
use App\Trips\Contracts\DepartureBookings;
use App\Trips\Contracts\PaymentReadiness;
use App\Trips\SeatsTakenDepartureBookings;
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

        // F-003 seams: the real payment-method check (F-001, F-003 Q9);
        // F-005 binds the real bookings query.
        $this->app->bind(PaymentReadiness::class, ActivePaymentMethods::class);
        $this->app->bind(DepartureBookings::class, SeatsTakenDepartureBookings::class);
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
