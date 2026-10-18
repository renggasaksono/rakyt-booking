<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Payments\PaymentMethodType;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Where customers transfer: a bank or e-wallet account of the organization
 * (F-001 C10–C11). Money goes straight to the seller (ADR-0003). F-006 adds
 * QRIS and cash, and the payment-instruction audit.
 */
#[Fillable(['type', 'provider', 'provider_name', 'account_number', 'account_holder', 'position', 'active'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $attributes = [
        'active' => true,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * "BCA", or the typed name for "Lainnya" / "BPD (isi nama)".
     */
    public function providerLabel(): string
    {
        return $this->provider_name ?? (string) config("rakyt.payment_providers.{$this->provider}.label", $this->provider);
    }
}
