<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Database\Factories\OrganizationMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['user_id', 'role'])]
class OrganizationMember extends Model
{
    /** @use HasFactory<OrganizationMemberFactory> */
    use BelongsToOrganization, HasFactory;

    public const ROLE_OWNER = 'owner';

    protected static function booted(): void
    {
        // F-010, ADR-0011: the platform admin is never a tenant.
        static::creating(function (OrganizationMember $member) {
            if (User::query()->whereKey($member->user_id)->value('is_platform_admin')) {
                throw new LogicException('A platform admin cannot be a member of an organization.');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
