<?php

namespace Tests\Fixtures\Tenancy;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Test-only seller-owned model that exercises the tenancy primitives
 * before real resources (trips, bookings…) exist.
 */
#[Fillable(['title', 'slug', 'related_note_id', 'archived'])]
class ProbeNote extends Model
{
    use BelongsToOrganization;

    protected $table = 'probe_notes';

    /**
     * For scopeBindings() child routes (F-009 R16).
     *
     * @return HasMany<ProbeNote, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProbeNote::class, 'related_note_id');
    }
}
