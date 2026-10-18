<?php

namespace App\Trips;

use InvalidArgumentException;

/**
 * Trip duration (F-003 AC-5, Data & rules). "Beberapa jam" is a label only and is
 * stored as 1 day, 0 nights.
 */
final readonly class Duration
{
    public const MAX_DAYS = 30;

    private function __construct(
        public string $label,
        public int $days,
        public int $nights,
    ) {}

    public static function hours(): self
    {
        return new self('hours', 1, 0);
    }

    public static function days(int $days, int $nights): self
    {
        if ($days < 1 || $days > self::MAX_DAYS || $nights < 0 || $nights > $days) {
            throw new InvalidArgumentException('Invalid duration.');
        }

        return new self('days', $days, $nights);
    }

    public static function fromColumns(?string $label, ?int $days, ?int $nights): ?self
    {
        return match ($label) {
            'hours' => self::hours(),
            'days' => self::days((int) $days, (int) $nights),
            default => null,
        };
    }

    /**
     * The picker's presets, in display order, before "Atur sendiri".
     *
     * @return array<string, self>
     */
    public static function presets(): array
    {
        return [
            'hours' => self::hours(),
            '1d' => self::days(1, 0),
            '2d1n' => self::days(2, 1),
            '3d2n' => self::days(3, 2),
            '4d3n' => self::days(4, 3),
        ];
    }

    /**
     * @return array{duration_label: string, duration_days: int, duration_nights: int}
     */
    public function toColumns(): array
    {
        return ['duration_label' => $this->label, 'duration_days' => $this->days, 'duration_nights' => $this->nights];
    }

    public function display(): string
    {
        if ($this->label === 'hours') {
            return __('trips.duration.hours');
        }

        return $this->nights === 0
            ? __('trips.duration.days', ['days' => $this->days])
            : __('trips.duration.days_nights', ['days' => $this->days, 'nights' => $this->nights]);
    }
}
