<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Security\Middleware\AssignRequestId;
use App\Security\Middleware\NoReferrer;
use App\Security\Middleware\SecurityHeaders;
use App\Security\SensitiveInput;
use App\Security\ThrottledResponse;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => ThrottledResponse::render($e, $request));

        // Never send passwords or tokens back into the session as old input.
        $exceptions->dontFlash(SensitiveInput::FIELDS);
    })->create();
