<?php

namespace App\Models;

use App\Organizations\BookingCode;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * The tenant boundary (ADR-0004). Not itself organization-scoped: route
 * binding finds any organization, so authorise through OrganizationPolicy.
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * The booking code is derived once at creation and the booking counter is
     * moved only by App\Bookings\BookingService (F-005 AC-17, AC-17b).
     */
    protected $guarded = ['id', 'public_id', 'booking_code', 'last_booking_number'];

    /** Database defaults, so new instances carry them too (ADR-0014, F-003 Q6). */
    protected $attributes = [
        'timezone' => 'Asia/Jakarta',
        'default_booking_deadline_preset' => 'h3',
        'plan' => 'free',
        'last_booking_number' => 0,
    ];

    protected function casts(): array
    {
        return [
            'onboarding_skipped_at' => 'datetime',
            'first_published_at' => 'datetime',
            'last_booking_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            $organization->public_id ??= (string) Str::uuid();
        });

        // F-005 AC-17b: references stay readable when the seller later changes their slug.
        static::updating(function (Organization $organization) {
            if ($organization->isDirty('booking_code') && $organization->getOriginal('booking_code') !== null) {
                throw new LogicException('An organization\'s booking code never changes (ADR-0026 §5).');
            }
        });
    }

    /**
     * Insert with the first free booking code derived from the slug (F-005
     * AC-17b). The unique index decides, not a prior check: when another
     * seller took the code first, the insert is retried with the next
     * candidate inside a savepoint, so an outer transaction survives.
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query)
    {
        if ($this->booking_code !== null) {
            return parent::performInsert($query);
        }

        // Each attempt fires `creating`/`created` again: keep those listeners free of side effects.
        foreach (BookingCode::candidates((string) $this->slug) as $code) {
            $this->booking_code = $code;

            try {
                return DB::transaction(fn () => parent::performInsert($query));
            } catch (UniqueConstraintViolationException $e) {
                if (! BookingCode::isConflict($e)) {
                    $this->booking_code = null;

                    throw $e;
                }
            }
        }

        $this->booking_code = null;

        throw new LogicException('No free booking code for this organization.');
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
