<?php

use App\Http\Middleware\EnsureOnboardingFinished;
use App\Http\Middleware\HandleInertiaRequests;
use App\Security\InertiaErrorResponse;
use App\Security\Middleware\AssignRequestId;
use App\Security\Middleware\NoReferrer;
use App\Security\Middleware\SecurityHeaders;
use App\Security\SensitiveInput;
use App\Security\ThrottledResponse;
use App\Tenancy\Middleware\EnsureCurrentOrganization;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            // A password change or reset signs out other sessions (F-001 A9, A12).
            AuthenticateSession::class,
            ResolveCurrentOrganization::class,
            HandleInertiaRequests::class,
        ]);

        // Route model binding must see the current organization (F-009 A2).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveCurrentOrganization::class,
        );

        $middleware->alias([
            'no-referrer' => NoReferrer::class,
            'organization' => EnsureCurrentOrganization::class,
            'onboarded' => EnsureOnboardingFinished::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => ThrottledResponse::render($e, $request));

        // F-009 R7: Inertia visits get an Inertia page, not the HTML error page in a modal.
        $exceptions->respond(fn (Response $response, Throwable $e, Request $request) => InertiaErrorResponse::render($response, $request));

        // Never send passwords or tokens back into the session as old input.
        $exceptions->dontFlash(SensitiveInput::FIELDS);
    })->create();
