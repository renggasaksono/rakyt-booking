<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Media\PrivateMedia;
use App\Media\UploadRules;
use App\Models\PaymentMethod;
use App\Notifications\Payments\PaymentInstructionsChangedNotification;
use App\Payments\PaymentInstructionLock;
use App\Payments\PaymentMethodRules;
use App\Payments\PaymentMethodService;
use App\Payments\PaymentMethodType;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Pengaturan → Pembayaran (F-006, ADR-0030 §4/§6): the organization's full
 * payment instructions — bank / e-wallet accounts and the QRIS image. Supersedes
 * F-001's interim "Rekening". Every change is audited (ADR-0018) and notified to
 * the sign-in email; changes are blocked for 24 hours after account recovery
 * (AC-24–AC-26). At least one payment method stays.
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodService $paymentMethods,
        private CurrentOrganization $current,
    ) {}

    public function index(PaymentInstructionLock $lock): Response
    {
        Gate::authorize('manage', PaymentMethod::class);

        $until = $lock->lockedUntil();

        return Inertia::render('Settings/Payment', [
            'methods' => $this->paymentMethods->forForm(),
            'qris' => $this->paymentMethods->qrisForForm(),
            'providers' => PaymentMethodService::providerOptions(),
            'limits' => PaymentMethodService::formLimits(),
            'urls' => ['update' => route('settings.payment.update'), 'back' => route('settings.index')],
            'lockedUntil' => $until?->utc()->format('Y-m-d\TH:i:s\Z'),
            'context' => ['now' => CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s\Z'), 'timezone' => $this->current->get()->timezone],
            'backLabel' => __('payment.back'),
            'copy' => __('payment'),
            'fields' => __('onboarding.payment'),
        ]);
    }

    public function update(Request $request, PaymentInstructionLock $lock): RedirectResponse
    {
        Gate::authorize('manage', PaymentMethod::class);

        // AC-25: refused server-side inside the 24-hour block, not only hidden.
        if ($lock->isLocked()) {
            return back()->with('notice', __('payment.locked', [
                'time' => $this->lockLabel($lock->lockedUntil()),
            ]));
        }

        $request->validate([
            'qris' => array_merge(['nullable'], array_slice(UploadRules::paymentEvidence(), 1)),
        ], ['qris' => __('payment.errors.qris_image')]);

        $methods = PaymentMethodRules::normalize($request->input('methods', []));
        $validated = PaymentMethodRules::validate($methods, requireOne: false);

        $removeQris = $request->boolean('remove_qris');
        $qrisFile = $request->file('qris');
        $keepsQris = $qrisFile !== null || ($this->paymentMethods->qrisForForm()['exists'] && ! $removeQris);

        // At least one payment method stays (a bank / e-wallet account or QRIS).
        if ($validated === [] && ! $keepsQris) {
            throw ValidationException::withMessages(['methods' => __('onboarding.payment.required')]);
        }

        $changed = $this->paymentMethods->saveInstructions($validated, $qrisFile, $removeQris);

        if ($changed) {
            $request->user()->notify(new PaymentInstructionsChangedNotification($this->current->get()->name));
        }

        return back()->with('toast', __('payment.saved'));
    }

    /**
     * AC-19: the seller previews their own QRIS image through a gated stream.
     */
    public function qris(): HttpResponse
    {
        Gate::authorize('manage', PaymentMethod::class);

        $qris = PaymentMethod::query()->where('type', PaymentMethodType::Qris->value)->orderBy('id')->first();

        abort_if($qris === null || $qris->qris_image_path === null, 404);

        return PrivateMedia::response($qris->qris_image_path);
    }

    private function lockLabel(?CarbonImmutable $until): string
    {
        if ($until === null) {
            return '';
        }

        return $until->setTimezone($this->current->get()->timezone)->format('d/m H.i');
    }
}
