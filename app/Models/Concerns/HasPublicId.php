<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A ULID `public_id` used in URLs so sequential ids are never exposed (F-003 Data & rules).
 *
 * @mixin Model
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model) {
            $model->public_id ??= Str::lower((string) Str::ulid());
        });
    }

    public function initializeHasPublicId(): void
    {
        $this->mergeGuarded(['public_id']);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
