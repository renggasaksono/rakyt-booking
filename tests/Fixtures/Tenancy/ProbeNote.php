<?php

namespace Tests\Fixtures\Tenancy;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only seller-owned model that exercises the tenancy primitives
 * before real resources (trips, bookings…) exist.
 */
#[Fillable(['title', 'slug', 'related_note_id', 'archived'])]
class ProbeNote extends Model
{
    use BelongsToOrganization;

    protected $table = 'probe_notes';
}
