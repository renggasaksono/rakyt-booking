<?php

namespace App\Payments;

use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Media\MediaPath;
use App\Media\PrivateMedia;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Tenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the organization's payment instructions — bank / e-wallet accounts
 * (F-001 C10–C12) and the QRIS image (F-006, ADR-0030 §4). Every change is
 * audited with before/after values (ADR-0018). The QRIS image path never enters
 * the audit (AC-19).
 */
final class PaymentMethodService
{
    public function __construct(
        private CurrentOrganization $current,
        private AuditLog $audit,
    ) {}

    /**
     * Sync the bank / e-wallet accounts only (onboarding step 4, F-001). QRIS
     * rows are left untouched. Blocks with an id update that account; others are
     * added; accounts not listed are removed — except those in $unchanged.
     *
     * @param  list<array{id?: ?string, provider: string, provider_name?: ?string, account_number: string, account_holder: string}>  $blocks  validated
     * @param  list<string>  $unchanged  public ids of accounts to keep untouched
     */
    public function sync(array $blocks, array $unchanged = []): void
    {
        DB::transaction(function () use ($blocks, $unchanged) {
            $this->lockOrganization();
            $before = $this->snapshotAll();
            $this->applyBankEwallet($blocks, $unchanged);
            $this->auditIfChanged($before);
        });
    }

    /**
     * Pengaturan → Pembayaran (F-006): save the bank / e-wallet blocks and the
     * QRIS image in one transaction with one audit event (AC-24). A new QRIS
     * image is stored before the lock; the replaced one is removed after commit.
     *
     * @param  list<array<string, mixed>>  $blocks  validated
     * @param  list<string>  $unchanged
     * @return bool whether anything changed (the controller sends the notice then)
     */
    public function saveInstructions(array $blocks, ?UploadedFile $qrisImage, bool $removeQris, array $unchanged = []): bool
    {
        $newPath = null;
        if ($qrisImage !== null) {
            $newPath = MediaPath::for($this->current->get(), 'payments/qris', $qrisImage->extension() ?: 'png');
            Storage::disk(PrivateMedia::disk())->put($newPath, $qrisImage->get());
        }

        [$changed, $oldPath] = DB::transaction(function () use ($blocks, $newPath, $removeQris, $unchanged) {
            $this->lockOrganization();
            $before = $this->snapshotAll();
            $this->applyBankEwallet($blocks, $unchanged);
            $old = $this->applyQris($newPath, $removeQris);

            return [$this->auditIfChanged($before), $old];
        });

        // Delete the replaced / removed image only after the change committed.
        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk(PrivateMedia::disk())->delete($oldPath);
        }

        // A stored image that was not used (nothing changed) is cleaned up.
        if ($newPath !== null && ! $changed) {
            Storage::disk(PrivateMedia::disk())->delete($newPath);
        }

        return $changed;
    }

    private function lockOrganization(): void
    {
        // One writer per organization at a time.
        Organization::query()->whereKey($this->current->id())->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<string>  $unchanged
     */
    private function applyBankEwallet(array $blocks, array $unchanged): void
    {
        $existing = PaymentMethod::query()->where('type', '!=', PaymentMethodType::Qris->value)
            ->orderBy('position')->orderBy('id')->get()->keyBy('public_id');
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
                'qris_image_path' => null,
                'position' => $position,
                'active' => true,
            ]);
            $method->save();
            $kept[] = $method->getKey();
        }

        $existing->reject(fn (PaymentMethod $method) => in_array($method->getKey(), $kept, true) || in_array($method->public_id, $unchanged, true))
            ->each(fn (PaymentMethod $method) => $method->delete());
    }

    /**
     * Create / replace or remove the organization's single QRIS row (AC-21,
     * AC-23). Returns the previous image path to delete after commit, if any.
     */
    private function applyQris(?string $newPath, bool $removeQris): ?string
    {
        $qris = PaymentMethod::query()->where('type', PaymentMethodType::Qris->value)->orderBy('id')->first();

        if ($removeQris) {
            $old = $qris?->qris_image_path;
            $qris?->delete();

            return $old;
        }

        if ($newPath === null) {
            return null;
        }

        $old = $qris?->qris_image_path;
        $method = $qris ?? new PaymentMethod;
        $method->fill([
            'type' => PaymentMethodType::Qris->value,
            'provider' => 'qris',
            'provider_name' => null,
            'account_number' => null,
            'account_holder' => null,
            'qris_image_path' => $newPath,
            // QRIS sorts after the bank / e-wallet rows.
            'position' => PaymentMethod::query()->where('type', '!=', PaymentMethodType::Qris->value)->count(),
            'active' => true,
        ]);
        $method->save();

        return $old !== null && $old !== $newPath ? $old : null;
    }

    private function auditIfChanged(array $before): bool
    {
        $after = $this->snapshotAll();

        if ($before === $after) {
            return false;
        }

        $this->audit->record(AuditAction::PaymentInstructionsChanged, $this->current->get(), ['methods' => $before], ['methods' => $after]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshotAll(): array
    {
        return PaymentMethod::query()->orderBy('position')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method) => self::snapshot($method))->values()->all();
    }

    /**
     * The organization's bank / e-wallet accounts as form blocks (step 4,
     * Pengaturan → Pembayaran). QRIS is handled separately (qrisForForm).
     *
     * @return list<array{id: string, provider: string, provider_name: ?string, account_number: string, account_holder: string}>
     */
    public function forForm(): array
    {
        return PaymentMethod::query()->where('type', '!=', PaymentMethodType::Qris->value)
            ->orderBy('position')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->public_id,
                'provider' => $method->provider,
                'provider_name' => $method->provider_name,
                'account_number' => $method->account_number,
                'account_holder' => $method->account_holder,
            ])->values()->all();
    }

    /**
     * The organization's QRIS method for the settings form (F-006): whether one
     * exists and its gated preview URL. The storage path never leaves the server.
     *
     * @return array{exists: bool, image_url: ?string}
     */
    public function qrisForForm(): array
    {
        $qris = PaymentMethod::query()->where('type', PaymentMethodType::Qris->value)->orderBy('id')->first();

        return [
            'exists' => $qris !== null && $qris->qris_image_path !== null,
            'image_url' => $qris !== null && $qris->qris_image_path !== null ? route('settings.payment.qris') : null,
        ];
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
            // A fingerprint, not the path (AC-19): detects a replaced QRIS image.
            'qris_image' => $method->qris_image_path === null ? null : substr(hash('sha256', $method->qris_image_path), 0, 12),
        ];
    }
}
