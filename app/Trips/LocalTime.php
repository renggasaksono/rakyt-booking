<?php

namespace App\Trips;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Indonesian display of instants in the organization's zone (F-003 Data & rules → Time).
 */
final class LocalTime
{
    /**
     * "21 Okt 23.59"
     */
    public static function short(DateTimeInterface $instant, string $timezone): string
    {
        return self::local($instant, $timezone)->translatedFormat('j M H.i');
    }

    /**
     * "Senin, 21 Okt pukul 23.59"
     */
    public static function long(DateTimeInterface $instant, string $timezone): string
    {
        $local = self::local($instant, $timezone);

        return __('trips.time.long', ['date' => $local->translatedFormat('l, j M'), 'time' => $local->format('H.i')]);
    }

    /**
     * "Y-m-d H:i" wall-clock value for form fields.
     */
    public static function input(DateTimeInterface $instant, string $timezone): string
    {
        return self::local($instant, $timezone)->format('Y-m-d H:i');
    }

    /**
     * Parse a wall-clock "Y-m-d H:i" (or "Y-m-d\TH:i") in the organization's zone.
     */
    public static function parse(string $value, string $timezone): ?CarbonImmutable
    {
        $value = str_replace('T', ' ', trim($value));
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);

        return $parsed !== false && $parsed->format('Y-m-d H:i') === $value ? $parsed : null;
    }

    private static function local(DateTimeInterface $instant, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone($timezone)->locale('id');
    }
}
