<?php

namespace App\Providers;

use App\Security\RateLimits;
use App\Tenancy\CurrentOrganization;
use App\Trips\Contracts\DepartureBookings;
use App\Trips\Contracts\PaymentReadiness;
use App\Trips\NoPaymentMethodsYet;
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

        // F-003 seams: F-001 binds the real payment-method check (Q9),
        // F-005 the real bookings query.
        $this->app->bind(PaymentReadiness::class, NoPaymentMethodsYet::class);
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
