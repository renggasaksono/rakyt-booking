<?php

namespace App\Models;

use App\Audit\AuditEventImmutable;
use App\Tenancy\BelongsToOrganization;
use App\Tenancy\OrganizationOptional;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of a sensitive change (F-009 A10–A13). Write through App\Audit\AuditLog.
 */
class AuditEvent extends Model implements OrganizationOptional
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'organization_id'];

    /**
     * Set by AuditLog for a platform-level action (F-010): the event then
     * belongs to no organization. Never set for a seller's action.
     */
    private bool $platformLevel = false;

    public function markPlatformLevel(): static
    {
        $this->platformLevel = true;

        return $this;
    }

    public function belongsToNoOrganization(): bool
    {
        return $this->platformLevel;
    }

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw AuditEventImmutable::make());
        static::deleting(fn () => throw AuditEventImmutable::make());
    }
}
