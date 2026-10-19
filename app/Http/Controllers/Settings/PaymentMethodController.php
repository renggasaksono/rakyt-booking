<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Payments\PaymentMethodRules;
use App\Payments\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Rekening (F-001 F5): the bank / e-wallet subset of F-006, with
 * the same rules and audit as onboarding step 4. It is how a seller who saved
 * a draft without an account adds one later (C12). At least one account stays.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private PaymentMethodService $paymentMethods) {}

    public function index(): Response
    {
        Gate::authorize('manage', PaymentMethod::class);

        return Inertia::render('Settings/PaymentMethods', [
            'methods' => $this->paymentMethods->forForm(),
            'providers' => PaymentMethodService::providerOptions(),
            'limits' => PaymentMethodService::formLimits(),
            'urls' => ['update' => route('payment-methods.update'), 'back' => route('settings.index')],
            'backLabel' => __('profile.settings.back'),
            'copy' => [...__('profile.payment_methods'), 'saving' => __('onboarding.saving'), 'save_failed' => __('onboarding.save_failed')],
            'fields' => __('onboarding.payment'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage', PaymentMethod::class);

        $methods = PaymentMethodRules::normalize($request->input('methods', []));
        $this->paymentMethods->sync(PaymentMethodRules::validate($methods));

        return back()->with('toast', __('profile.payment_methods.saved'));
    }
}
