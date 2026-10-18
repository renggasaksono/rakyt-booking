<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Whether a URL value can be looked up without PostgreSQL throwing (a 500):
 * integer keys must fit a bigint (IntegerKey); other route keys (public ids,
 * slugs) must be short, valid UTF-8 without NUL bytes. Anything else is a 404.
 */
final class RouteKey
{
    private const MAX_LENGTH = 255;

    public static function isAcceptable(Model $model, mixed $value, ?string $field): bool
    {
        $field ??= $model->getRouteKeyName();

        if ($field === $model->getKeyName() && $model->getKeyType() === 'int') {
            return IntegerKey::isValid($value);
        }

        if (is_int($value)) {
            return true;
        }

        return is_string($value)
            && $value !== ''
            && strlen($value) <= self::MAX_LENGTH
            && ! str_contains($value, "\0")
            && mb_check_encoding($value, 'UTF-8');
    }
}
