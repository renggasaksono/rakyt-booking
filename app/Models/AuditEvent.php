<?php

namespace App\Models;

use App\Audit\AuditEventImmutable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of a sensitive change (F-009 A10–A13). Write through App\Audit\AuditLog.
 */
class AuditEvent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'organization_id'];

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
