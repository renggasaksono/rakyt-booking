<?php

namespace App\Organizations;

use App\Models\Organization;
use App\Security\PhoneNumber;
use Closure;

/**
 * Organization fields, shared by onboarding step 1 (F-001 B2–B7) and
 * Pengaturan → Profil usaha (F1, F2). Call normalize() on the input first.
 */
final class OrganizationRules
{
    public const NAME_MIN = 2;

    public const NAME_MAX = 80;

    public const CITY_MAX = 80;

    public const BIO_MAX = 160;

    /** B3: +62 8 followed by 8–11 digits. */
    private const WHATSAPP_PATTERN = '/^\+628\d{8,11}$/';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $out = $input;

        foreach (['name', 'city', 'bio'] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $value = trim(preg_replace('/\s+/u', ' ', $input[$field]) ?? '');
                $out[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('whatsapp', $input)) {
            $out['whatsapp'] = is_string($input['whatsapp']) ? PhoneNumber::normalize($input['whatsapp']) : null;
        }

        if (array_key_exists('slug', $input)) {
            $out['slug'] = is_string($input['slug']) ? BookingSlug::normalize($input['slug']) : $input['slug'];
        }

        return $out;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(?Organization $organization = null, bool $withSlug = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX],
            'city' => ['required', 'string', 'min:2', 'max:'.self::CITY_MAX],
            'whatsapp' => ['required', 'string', self::whatsapp()],
        ];

        if ($withSlug) {
            $rules['slug'] = ['required', 'string', self::slug($organization)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => __('onboarding.organization.name'),
            'city' => __('onboarding.organization.city'),
            'whatsapp' => __('onboarding.organization.whatsapp'),
            'slug' => __('onboarding.organization.slug'),
            'bio' => __('profile.bio'),
            'timezone' => __('profile.timezone'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'whatsapp.required' => __('onboarding.organization.whatsapp_invalid'),
        ];
    }

    private static function whatsapp(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || preg_match(self::WHATSAPP_PATTERN, $value) !== 1) {
                $fail(__('onboarding.organization.whatsapp_invalid'));
            }
        };
    }

    private static function slug(?Organization $organization): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($organization) {
            $state = BookingSlug::state((string) $value, $organization);

            if ($state !== BookingSlug::AVAILABLE) {
                $fail(__('onboarding.organization.slug_'.$state));
            }
        };
    }
}
