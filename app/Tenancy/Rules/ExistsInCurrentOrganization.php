<?php

namespace App\Tenancy\Rules;

use App\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Org-scoped replacement for `exists:` on ids in payloads (F-009 A3).
 */
class ExistsInCurrentOrganization implements ValidationRule
{
    public function __construct(
        private string $table,
        private string $column = 'id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $organizationId = app(CurrentOrganization::class)->id();

        $exists = $organizationId !== null
            && is_scalar($value)
            && DB::table($this->table)
                ->where($this->column, $value)
                ->where('organization_id', $organizationId)
                ->exists();

        if (! $exists) {
            $fail('tenancy.not_found')->translate();
        }
    }
}
