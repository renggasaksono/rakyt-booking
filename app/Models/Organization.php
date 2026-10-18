<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The tenant boundary (ADR-0004). Not itself organization-scoped: route
 * binding finds any organization, so authorise through OrganizationPolicy.
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $guarded = ['id', 'public_id'];

    /** Database defaults, so new instances carry them too (ADR-0014, F-003 Q6). */
    protected $attributes = [
        'timezone' => 'Asia/Jakarta',
        'default_booking_deadline_preset' => 'h3',
        'plan' => 'free',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_skipped_at' => 'datetime',
            'first_published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            $organization->public_id ??= (string) Str::uuid();
        });
    }

    /**
     * Still in the guided first-trip flow (F-001 E2): never published and
     * "Simpan sebagai draf" not chosen.
     */
    public function isOnboarding(): bool
    {
        return $this->first_published_at === null && $this->onboarding_skipped_at === null;
    }

    /**
     * The slug can be changed by the seller only until the first publish (F-001 Q3, F2).
     */
    public function slugLocked(): bool
    {
        return $this->first_published_at !== null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk(config('rakyt.media.public_disk'))->url($this->logo_path);
    }

    /**
     * Scoped like every OrganizationMember query: only the current
     * organization's members are visible. There is deliberately no unscoped
     * users() relation (ADR-0021).
     *
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
