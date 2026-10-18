<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A public trip photo (ADR-0015). The lowest position is the cover.
 */
#[Fillable(['trip_id', 'path', 'variants', 'position'])]
class TripPhoto extends Model
{
    use BelongsToOrganization, HasPublicId;

    protected $attributes = [
        'variants' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function url(?int $width = null): string
    {
        $path = $width !== null ? ($this->variants[(string) $width] ?? $this->path) : $this->path;

        return Storage::disk(config('rakyt.media.public_disk'))->url($path);
    }

    /**
     * The original and every variant.
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        return array_values([$this->path, ...array_values($this->variants ?? [])]);
    }
}
