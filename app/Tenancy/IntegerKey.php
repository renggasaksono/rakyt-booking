<?php

namespace App\Tenancy;

/**
 * Whether input can be a bigint primary key. PHP's int range equals PostgreSQL's
 * bigint, so anything that passes never makes the database throw (a 500).
 */
final class IntegerKey
{
    public static function isValid(mixed $value): bool
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }
}
