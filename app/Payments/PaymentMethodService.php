<?php

namespace App\Payments;

use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Writes the organization's bank / e-wallet accounts (F-001 C10–C12). Every
 * change is audited with before/after values (ADR-0018).
 */
final class PaymentMethodService
{
    public function __construct(
        private CurrentOrganization $current,
        private AuditLog $audit,
    ) {}

    /**
     * Make the organization's accounts exactly the given blocks: blocks with
     * an id update that account, the others are added, accounts not listed
     * are removed — except those in $unchanged, which stay as they are.
     *
     * @param  list<array{id?: ?string, provider: string, provider_name?: ?string, account_number: string, account_holder: string}>  $blocks  validated
     * @param  list<string>  $unchanged  public ids of accounts to keep untouched
     */
    public function sync(array $blocks, array $unchanged = []): void
    {
        DB::transaction(function () use ($blocks, $unchanged) {
            // One writer per organization at a time.
            Organization::query()->whereKey($this->current->id())->lockForUpdate()->firstOrFail();

            $existing = PaymentMethod::query()->orderBy('position')->orderBy('id')->get()->keyBy('public_id');
            $before = $existing->map(fn (PaymentMethod $method) => self::snapshot($method))->values()->all();
            $kept = [];

            foreach (array_values($blocks) as $position => $block) {
                $method = isset($block['id']) ? $existing->get($block['id']) : null;
                $method ??= new PaymentMethod;

                $provider = $block['provider'];
                $method->fill([
                    'type' => config("rakyt.payment_providers.{$provider}.type", PaymentMethodType::Bank->value),
                    'provider' => $provider,
                    'provider_name' => (config("rakyt.payment_providers.{$provider}.name") ?? false) ? ($block['provider_name'] ?? null) : null,
                    'account_number' => $block['account_number'],
                    'account_holder' => $block['account_holder'],
                    'position' => $position,
                    'active' => true,
                ]);
                $method->save();
                $kept[] = $method->getKey();
            }

            $existing->reject(fn (PaymentMethod $method) => in_array($method->getKey(), $kept, true) || in_array($method->public_id, $unchanged, true))
                ->each(fn (PaymentMethod $method) => $method->delete());

            $after = PaymentMethod::query()->orderBy('position')->orderBy('id')->get()
                ->map(fn (PaymentMethod $method) => self::snapshot($method))->values()->all();

            if ($before !== $after) {
                $this->audit->record(AuditAction::PaymentInstructionsChanged, $this->current->get(), ['methods' => $before], ['methods' => $after]);
            }
        });
    }

    /**
     * The organization's accounts as form blocks (step 4, Pengaturan → Rekening).
     *
     * @return list<array{id: string, provider: string, provider_name: ?string, account_number: string, account_holder: string}>
     */
    public function forForm(): array
    {
        return PaymentMethod::query()->orderBy('position')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->public_id,
                'provider' => $method->provider,
                'provider_name' => $method->provider_name,
                'account_number' => $method->account_number,
                'account_holder' => $method->account_holder,
            ])->values()->all();
    }

    /**
     * The "Bank / e-wallet" picker (C10).
     *
     * @return list<array{value: string, label: string, type: string, asks_name: bool}>
     */
    public static function providerOptions(): array
    {
        $providers = [];

        foreach (config('rakyt.payment_providers') as $key => $provider) {
            $providers[] = ['value' => $key, 'label' => $provider['label'], 'type' => $provider['type'], 'asks_name' => $provider['name'] ?? false];
        }

        return $providers;
    }

    /**
     * @return array{methods: int, holder: int, provider_name: int}
     */
    public static function formLimits(): array
    {
        return [
            'methods' => (int) config('rakyt.onboarding.max_payment_methods'),
            'holder' => PaymentMethodRules::HOLDER_MAX,
            'provider_name' => PaymentMethodRules::PROVIDER_NAME_MAX,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(PaymentMethod $method): array
    {
        return [
            'type' => $method->type->value,
            'provider' => $method->provider,
            'provider_name' => $method->provider_name,
            'account_number' => $method->account_number,
            'account_holder' => $method->account_holder,
            'active' => $method->active,
        ];
    }
}
