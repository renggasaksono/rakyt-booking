<?php

namespace App\Trips;

/**
 * "Tutup pemesanan" presets (F-003 AC-21, Data & rules → Deadline presets).
 */
enum DeadlinePreset: string
{
    case SameDay = 'same_day';
    case H1 = 'h1';
    case H3 = 'h3';
    case H7 = 'h7';
    case Custom = 'custom';

    /**
     * Days before the start date for the H-n presets.
     */
    public function daysBefore(): ?int
    {
        return match ($this) {
            self::H1 => 1,
            self::H3 => 3,
            self::H7 => 7,
            default => null,
        };
    }

    /**
     * Relative presets follow the start date and the meeting time (AC-24).
     */
    public function isRelative(): bool
    {
        return $this !== self::Custom;
    }

    /**
     * Presets an organization may choose as its default. "Sampai hari keberangkatan"
     * needs a meeting time, which a new trip may not have, so it is not offered.
     *
     * @return list<self>
     */
    public static function organizationDefaults(): array
    {
        return [self::H1, self::H3, self::H7];
    }
}
