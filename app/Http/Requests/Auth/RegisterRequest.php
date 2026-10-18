<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-001 A2: name 2–80, email valid and unused (case-insensitive), password
 * ≥ 8 with no composition rules, terms ticked.
 */
class RegisterRequest extends FormRequest
{
    public const NAME_MIN = 2;

    public const NAME_MAX = 80;

    public const PASSWORD_MIN = 8;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX],
            'email' => ['required', 'string', 'email', 'max:255', self::unusedEmail()],
            'password' => ['required', 'string', 'min:'.self::PASSWORD_MIN, 'max:255'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => __('account.register.terms_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('account.register.name')];
    }

    /**
     * Users are not organization-scoped, so this is a plain query (no bare
     * `unique:` rule, F-009 R14).
     */
    public static function unusedEmail(?User $except = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($except) {
            $taken = User::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower((string) $value)])
                ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('account.register.email_taken'));
            }
        };
    }
}
