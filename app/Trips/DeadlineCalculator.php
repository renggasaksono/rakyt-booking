<?php

namespace App\Trips;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Turns a deadline preset into an instant (F-003 Data & rules → Deadline presets, ADR-0014).
 *
 * Dates and times are wall-clock values in the organization's time zone; the
 * result is returned in UTC for storage.
 */
final class DeadlineCalculator
{
    /**
     * @param  string  $startDate  Y-m-d in the organization's zone
     * @param  string|null  $meetingTime  H:i, or null when the trip has none
     * @param  CarbonImmutable|null  $custom  the seller's chosen instant (custom preset only)
     * @return CarbonImmutable|null null when the preset is not available (same day without a meeting time)
     */
    public static function compute(
        DeadlinePreset $preset,
        string $startDate,
        ?string $meetingTime,
        string $timezone,
        ?CarbonImmutable $custom = null,
    ): ?CarbonImmutable {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate, $timezone);

        $local = match ($preset) {
            DeadlinePreset::H1, DeadlinePreset::H3, DeadlinePreset::H7 => $start->subDays($preset->daysBefore())->setTime(23, 59, 59),
            // Q7: one hour before the meeting time (mockup 3a: kumpul 06.00 → 05.00).
            DeadlinePreset::SameDay => $meetingTime === null ? null : self::meetingAt($start, $meetingTime)->subHour(),
            DeadlinePreset::Custom => $custom ?? throw new InvalidArgumentException('A custom deadline needs a date and time.'),
        };

        return $local?->utc();
    }

    /**
     * The latest allowed deadline: the meeting time on the start date, or the
     * end of the start date when the trip has no meeting time (AC-22, AC-23).
     */
    public static function latestAllowed(string $startDate, ?string $meetingTime, string $timezone): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate, $timezone);

        return ($meetingTime === null ? $start->setTime(23, 59, 59) : self::meetingAt($start, $meetingTime))->utc();
    }

    /**
     * Presets that can be offered for a trip (AC-22: no "same day" without a meeting time).
     *
     * @return list<DeadlinePreset>
     */
    public static function availablePresets(?string $meetingTime): array
    {
        return array_values(array_filter(
            DeadlinePreset::cases(),
            fn (DeadlinePreset $preset) => $preset !== DeadlinePreset::SameDay || $meetingTime !== null,
        ));
    }

    private static function meetingAt(CarbonImmutable $start, string $meetingTime): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $meetingTime));

        return $start->setTime($hour, $minute);
    }
}
