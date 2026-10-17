<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Database\Factories\OrganizationMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role'])]
class OrganizationMember extends Model
{
    /** @use HasFactory<OrganizationMemberFactory> */
    use BelongsToOrganization, HasFactory;

    public const ROLE_OWNER = 'owner';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
