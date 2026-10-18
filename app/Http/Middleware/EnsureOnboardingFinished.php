<?php

namespace App\Http\Middleware;

use App\Onboarding\OnboardingProgress;
use App\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-001 E2: a seller who has neither published nor chosen "Simpan sebagai
 * draf" resumes the guided flow when opening a seller page. Only page visits
 * are redirected; JSON and form writes pass (step 2 uploads photos through
 * the trip routes).
 */
class EnsureOnboardingFinished
{
    public function __construct(
        private CurrentOrganization $current,
        private OnboardingProgress $progress,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->current->get();

        if ($organization?->isOnboarding() && $request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->to($this->progress->landingUrl($request->user()));
        }

        return $next($request);
    }
}
