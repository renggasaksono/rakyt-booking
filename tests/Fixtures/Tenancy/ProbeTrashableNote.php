<?php

namespace Tests\Fixtures\Tenancy;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test-only soft-deleting seller-owned model.
 */
#[Fillable(['title'])]
class ProbeTrashableNote extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $table = 'probe_trashable_notes';
}
