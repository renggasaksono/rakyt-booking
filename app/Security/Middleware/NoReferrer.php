<?php

namespace App\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For customer booking/payment pages whose URL carries an access token, so
 * the token never leaks to wa.me or image hosts (F-009 A16, ADR-0017).
 */
class NoReferrer
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
