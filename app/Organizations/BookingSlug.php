<?php

namespace App\Organizations;

use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * The booking link `rakyt.id/<slug>` (F-001 B4–B7, B10, B12).
 *
 * 3–30 characters of a-z, 0-9 and "-", starting and ending with a letter or
 * digit, no "--"; unique case-insensitively; not reserved. The database
 * enforces the format and uniqueness too.
 */
final class BookingSlug
{
    public const MIN = 3;

    public const MAX = 30;

    public const AVAILABLE = 'available';

    public const TAKEN = 'taken';

    public const RESERVED = 'reserved';

    public const INVALID = 'invalid';

    private const PATTERN = '/^[a-z0-9](?:[a-z0-9]|-(?!-))*[a-z0-9]$/';

    /**
     * B4: suggested from the business name — lower-cased, accents stripped,
     * spaces to "-", anything else removed.
     */
    public static function fromName(string $name): string
    {
        $slug = Str::of(Str::ascii($name))->lower()
            ->replaceMatches('/\s+/', '-')
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();

        return rtrim(substr($slug, 0, self::MAX), '-');
    }

    /**
     * B5: uppercase input is lower-cased; nothing else is changed, so a bad
     * character is reported rather than silently dropped.
     */
    public static function normalize(?string $input): string
    {
        return Str::lower(trim((string) $input));
    }

    public static function isValidFormat(string $slug): bool
    {
        $length = strlen($slug);

        return $length >= self::MIN && $length <= self::MAX && preg_match(self::PATTERN, $slug) === 1;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, config('rakyt.reserved_slugs'), true);
    }

    /**
     * B6/B12: one of available | taken | reserved | invalid. A slug the given
     * organization already holds counts as available for it.
     */
    public static function state(string $slug, ?Organization $owner = null): string
    {
        return match (true) {
            ! self::isValidFormat($slug) => self::INVALID,
            self::isReserved($slug) => self::RESERVED,
            self::isTaken($slug, $owner) => self::TAKEN,
            default => self::AVAILABLE,
        };
    }

    public static function isTaken(string $slug, ?Organization $owner = null): bool
    {
        // Organization is the tenant itself, not an organization-scoped model.
        return Organization::query()
            ->whereRaw('lower(slug) = ?', [Str::lower($slug)])
            ->when($owner?->exists, fn ($query) => $query->whereKeyNot($owner->getKey()))
            ->exists();
    }

    /**
     * B6: up to three available alternatives, e.g. kencana-trip, kencana-jepara, kencana2.
     *
     * @return list<string>
     */
    public static function suggestions(string $slug, ?string $name = null, ?string $city = null, ?Organization $owner = null, int $limit = 3): array
    {
        $base = self::fromName($slug) ?: self::fromName((string) $name);
        $base = rtrim(substr($base, 0, self::MAX - 4), '-');

        if ($base === '') {
            return [];
        }

        $cityWord = self::fromName(Str::before((string) $city, ','));
        $nameSlug = self::fromName((string) $name);

        $candidates = array_filter([
            $nameSlug !== $base ? $nameSlug : null,
            $base.'-trip',
            $cityWord !== '' ? $base.'-'.$cityWord : null,
            ...array_map(fn (int $n) => $base.$n, range(2, 9)),
            $base.'-id',
            $base.'-tour',
        ]);

        $out = [];

        foreach (array_unique($candidates) as $candidate) {
            $candidate = rtrim(substr($candidate, 0, self::MAX), '-');

            if ($candidate !== $slug && ! in_array($candidate, $out, true) && self::state($candidate, $owner) === self::AVAILABLE) {
                $out[] = $candidate;
            }

            if (count($out) === $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * "rakyt.id/kencana"
     */
    public static function display(string $slug): string
    {
        return config('rakyt.public_host').'/'.$slug;
    }

    /**
     * "https://rakyt.id/kencana" (D5).
     */
    public static function url(string $slug): string
    {
        return 'https://'.self::display($slug);
    }
}
