<?php

namespace App\Tenancy\Rules;

use App\Tenancy\BelongsToOrganization;
use App\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Org-scoped replacement for `unique:` / Rule::unique (F-009 R14). Queries
 * through the model, so the organization scope applies; a value used by
 * another organization is neither rejected nor revealed.
 *
 *     new UniqueInCurrentOrganization(Trip::class, 'slug', ignore: $trip)
 */
class UniqueInCurrentOrganization implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model  a model that uses BelongsToOrganization
     */
    public function __construct(
        private string $model,
        private string $column,
        private ?Model $ignore = null,
    ) {
        if (! in_array(BelongsToOrganization::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException("{$model} is not organization-scoped.");
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->isTaken($value)) {
            $fail('validation.unique')->translate();
        }
    }

    /**
     * Without a current organization nothing can be checked, so the value counts as taken (fails closed).
     */
    public function isTaken(mixed $value): bool
    {
        if (! app(CurrentOrganization::class)->check() || ! is_scalar($value)) {
            return true;
        }

        return $this->model::query()
            ->where($this->column, $value)
            ->when($this->ignore, fn ($query, Model $ignore) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }
}
