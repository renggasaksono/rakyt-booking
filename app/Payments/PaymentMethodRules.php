<?php

namespace App\Payments;

use App\Tenancy\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Bank / e-wallet blocks (F-001 C10–C11). Call normalize() first.
 */
final class PaymentMethodRules
{
    public const HOLDER_MIN = 2;

    public const HOLDER_MAX = 100;

    public const PROVIDER_NAME_MAX = 60;

    /**
     * @return mixed list of blocks with digits-only account numbers and trimmed text
     */
    public static function normalize(mixed $methods): mixed
    {
        if (! is_array($methods)) {
            return $methods;
        }

        return array_values(array_map(function ($block) {
            if (! is_array($block)) {
                return $block;
            }

            foreach (['provider_name', 'account_holder'] as $field) {
                if (isset($block[$field]) && is_string($block[$field])) {
                    $value = trim(preg_replace('/\s+/u', ' ', $block[$field]) ?? '');
                    $block[$field] = $value === '' ? null : $value;
                }
            }

            // C10: spaces (and dashes) are ignored.
            if (isset($block['account_number']) && is_string($block['account_number'])) {
                $block['account_number'] = preg_replace('/[\s-]+/', '', $block['account_number']);
            }

            return $block;
        }, $methods));
    }

    /**
     * @param  bool  $requireOne  publishing needs at least one account (D1)
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $requireOne): array
    {
        return [
            'methods' => [$requireOne ? 'required' : 'present', 'array', 'max:'.config('rakyt.onboarding.max_payment_methods')],
            'methods.*' => ['array'],
            'methods.*.id' => ['nullable', 'string', new ExistsInCurrentOrganization('payment_methods', 'public_id')],
            'methods.*.provider' => ['required', 'string', Rule::in(array_keys(config('rakyt.payment_providers')))],
            'methods.*.provider_name' => ['nullable', 'string', 'max:'.self::PROVIDER_NAME_MAX],
            'methods.*.account_number' => ['required', 'string', 'regex:/^[0-9]{5,20}$/'],
            'methods.*.account_holder' => ['required', 'string', 'min:'.self::HOLDER_MIN, 'max:'.self::HOLDER_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'methods.required' => __('onboarding.payment.required'),
            'methods.max' => __('onboarding.payment.max', ['max' => config('rakyt.onboarding.max_payment_methods')]),
            'methods.*.account_number.regex' => __('onboarding.payment.account_number_invalid'),
            'methods.*.account_number.required' => __('onboarding.payment.account_number_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'methods.*.provider' => __('onboarding.payment.provider'),
            'methods.*.provider_name' => __('onboarding.payment.provider_name'),
            'methods.*.account_number' => __('onboarding.payment.account_number'),
            'methods.*.account_holder' => __('onboarding.payment.account_holder'),
        ];
    }

    /**
     * "Lainnya" and "BPD (isi nama)" need the typed name (checked after the
     * rules, since a rule does not run on an empty field).
     *
     * @param  list<array<string, mixed>>  $methods
     * @return array<string, string> errors keyed like methods.0.provider_name
     */
    public static function missingProviderNames(array $methods): array
    {
        $errors = [];

        foreach ($methods as $i => $block) {
            $provider = $block['provider'] ?? null;

            if (is_string($provider) && (config("rakyt.payment_providers.{$provider}.name") ?? false) && empty($block['provider_name'])) {
                $errors["methods.{$i}.provider_name"] = __('validation.required', ['attribute' => __('onboarding.payment.provider_name')]);
            }
        }

        return $errors;
    }
}
