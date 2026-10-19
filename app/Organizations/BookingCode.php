<?php

namespace App\Organizations;

use Generator;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The seller's booking code inside booking references, `RKB-BRM-1024-0142`
 * (F-005 AC-17b, ADR-0026 §5): 3–4 upper-case characters derived from the slug
 * once, when the organization is created; unique; never `RKB`.
 *
 * Candidates are tried in order and the unique index decides (Organization
 * retries the insert with the next one), so two sellers racing for one code
 * never both get it.
 */
final class BookingCode
{
    public const PATTERN = '/^[A-Z]{3}[A-Z0-9]?$/';

    /** Rakyt's own prefix. */
    public const RESERVED = ['RKB'];

    /** Unique index on organizations.booking_code. */
    public const UNIQUE_INDEX = 'organizations_booking_code_unique';

    private const VOWELS = ['A', 'E', 'I', 'O', 'U'];

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Random four-letter codes tried after the derived ones. */
    private const RANDOM_TRIES = 50;

    /**
     * `bromo-adventure` → BRM, then other letter combinations of the slug
     * (BRD, BRV, …), then BRM2…BRM9, then random four-letter codes.
     *
     * @return Generator<int, string>
     */
    public static function candidates(string $slug): Generator
    {
        $seen = [];
        $letters = str_split(preg_replace('/[^A-Z]/', '', strtoupper($slug)) ?? '');

        foreach (self::derived($letters) as $code) {
            if (! isset($seen[$code]) && self::isAllowed($code)) {
                $seen[$code] = true;

                yield $code;
            }
        }

        for ($i = 0; $i < self::RANDOM_TRIES; $i++) {
            $code = '';

            for ($j = 0; $j < 4; $j++) {
                $code .= self::ALPHABET[random_int(0, 25)];
            }

            if (! isset($seen[$code])) {
                $seen[$code] = true;

                yield $code;
            }
        }
    }

    public static function isAllowed(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1 && ! in_array($code, self::RESERVED, true);
    }

    /**
     * Whether an insert failed on the booking code, not on another unique column (the slug).
     */
    public static function isConflict(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), self::UNIQUE_INDEX);
    }

    /**
     * @param  list<string>  $letters
     * @return Generator<int, string>
     */
    private static function derived(array $letters): Generator
    {
        if ($letters === []) {
            return;
        }

        $first = $letters[0];
        $rest = array_slice($letters, 1);
        $consonants = array_values(array_filter($rest, fn (string $letter) => ! in_array($letter, self::VOWELS, true)));
        $vowels = array_values(array_filter($rest, fn (string $letter) => in_array($letter, self::VOWELS, true)));

        // The stem: first letter, then the next consonants (then vowels, then X, when there are too few).
        $stem = substr($first.implode('', $consonants).implode('', $vowels).'XX', 0, 3);

        yield $stem;

        // Other combinations: first letter + two later letters in slug order, consonants first.
        foreach ([$consonants, $rest] as $pool) {
            $count = count($pool);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    yield $first.$pool[$i].$pool[$j];
                }
            }
        }

        for ($digit = 2; $digit <= 9; $digit++) {
            yield $stem.$digit;
        }
    }
}
