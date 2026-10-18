<?php

namespace App\Trips;

use Illuminate\Validation\Rule;

/**
 * Trip field validation shared by the trip editor and F-001 onboarding (4c/4d).
 *
 * Call normalize() on the raw input first (trims list items, drops blanks,
 * turns empty strings into null), then validate with rules().
 */
final class TripRules
{
    public const NAME_MIN = 3;

    public const NAME_MAX = 80;

    public const SHORT_DESCRIPTION_MAX = 300;

    public const LIST_MAX_ITEMS = 20;

    public const LIST_ITEM_MAX = 40;

    public const MEETING_POINT_MAX = 200;

    public const ITINERARY_LINE_MAX = 300;

    public const TERMS_MAX = 2000;

    public const PRICE_MIN = 1_000;

    public const PRICE_MAX = 100_000_000;

    /** Fields a seller can edit on a trip. */
    public const FIELDS = [
        'type', 'booking_mode', 'name', 'short_description',
        'duration_label', 'duration_days', 'duration_nights',
        'inclusions', 'exclusions', 'meeting_point', 'meeting_time', 'map_url',
        'itinerary', 'terms', 'base_price',
    ];

    /**
     * @param  bool  $partial  only validate the fields present (draft autosave)
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'type' => [...$sometimes, $partial ? 'required' : 'nullable', Rule::enum(TripType::class)],
            'booking_mode' => [...$sometimes, 'nullable', Rule::enum(BookingMode::class)],
            'name' => [...$sometimes, 'required', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX],
            'short_description' => [...$sometimes, 'nullable', 'string', 'max:'.self::SHORT_DESCRIPTION_MAX],
            'duration_label' => [...$sometimes, 'nullable', Rule::in(['hours', 'days'])],
            'duration_days' => [...$sometimes, 'nullable', 'required_if:duration_label,days', 'integer', 'between:1,'.Duration::MAX_DAYS],
            'duration_nights' => [...$sometimes, 'nullable', 'required_if:duration_label,days', 'integer', 'min:0', 'lte:duration_days'],
            'inclusions' => [...$sometimes, 'array', 'max:'.self::LIST_MAX_ITEMS],
            'inclusions.*' => ['string', 'max:'.self::LIST_ITEM_MAX],
            'exclusions' => [...$sometimes, 'array', 'max:'.self::LIST_MAX_ITEMS],
            'exclusions.*' => ['string', 'max:'.self::LIST_ITEM_MAX],
            'meeting_point' => [...$sometimes, 'nullable', 'string', 'max:'.self::MEETING_POINT_MAX],
            'meeting_time' => [...$sometimes, 'nullable', 'date_format:H:i'],
            'map_url' => [...$sometimes, 'nullable', 'string', 'max:2048', 'url:http,https'],
            'itinerary' => [...$sometimes, 'array', 'max:'.Duration::MAX_DAYS],
            'itinerary.*' => ['nullable', 'string', 'max:'.self::ITINERARY_LINE_MAX],
            'terms' => [...$sometimes, 'nullable', 'string', 'max:'.self::TERMS_MAX],
            'base_price' => [...$sometimes, 'nullable', 'integer', 'between:'.self::PRICE_MIN.','.self::PRICE_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => __('trips.errors.name_required'),
            'name.min' => __('trips.errors.name_min', ['min' => self::NAME_MIN]),
            'duration_nights.lte' => __('trips.duration.nights_max'),
            'inclusions.max' => __('trips.errors.items_max', ['max' => self::LIST_MAX_ITEMS]),
            'exclusions.max' => __('trips.errors.items_max', ['max' => self::LIST_MAX_ITEMS]),
            'inclusions.*.max' => __('trips.errors.item_too_long', ['max' => self::LIST_ITEM_MAX]),
            'exclusions.*.max' => __('trips.errors.item_too_long', ['max' => self::LIST_ITEM_MAX]),
            'base_price.between' => __('trips.errors.price_range'),
            'base_price.integer' => __('trips.errors.price_range'),
        ];
    }

    /**
     * Only the keys present in $input are returned, so partial saves stay partial.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            $out[$field] = match ($field) {
                'inclusions', 'exclusions' => self::normalizeList($value),
                'itinerary' => self::normalizeItinerary($value),
                default => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value,
            };
        }

        // "Beberapa jam" is stored as 1 day, 0 nights.
        if (($out['duration_label'] ?? null) === 'hours') {
            $out['duration_days'] = 1;
            $out['duration_nights'] = 0;
        } elseif (array_key_exists('duration_label', $out) && $out['duration_label'] === null) {
            $out['duration_days'] = null;
            $out['duration_nights'] = null;
        }

        return $out;
    }

    /**
     * Trimmed, blanks dropped, duplicates removed.
     *
     * @return mixed list<string> for a list; anything else is left for validation to reject
     */
    private static function normalizeList(mixed $value): mixed
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return $value;
        }

        $items = [];

        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            if (! is_string($item)) {
                return $value;
            }

            $item = trim(preg_replace('/\s+/u', ' ', $item) ?? '');

            if ($item !== '' && ! in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * One optional line per day; trailing empty days are dropped.
     */
    private static function normalizeItinerary(mixed $value): mixed
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return $value;
        }

        $lines = array_map(fn ($line) => is_string($line) ? trim($line) : $line, array_values($value));

        while ($lines !== [] && ($lines[array_key_last($lines)] === '' || $lines[array_key_last($lines)] === null)) {
            array_pop($lines);
        }

        return array_map(fn ($line) => $line === null ? '' : $line, $lines);
    }
}
