<?php

namespace App\Trips;

/**
 * Trip type presets (F-003 AC-1, shared with F-001 onboarding 4c).
 */
enum TripType: string
{
    case OpenTrip = 'open_trip';
    case Private = 'private';
    case DayTrip = 'day_trip';

    /**
     * Values prefilled when the type is chosen on a new trip. All stay editable.
     *
     * @return array{booking_mode: BookingMode, duration: ?Duration}
     */
    public function preset(): array
    {
        return match ($this) {
            self::OpenTrip => ['booking_mode' => BookingMode::Scheduled, 'duration' => Duration::days(3, 2)],
            self::DayTrip => ['booking_mode' => BookingMode::Scheduled, 'duration' => Duration::days(1, 0)],
            // Private trips start without a duration; the seller picks one if they want.
            self::Private => ['booking_mode' => BookingMode::RequestDate, 'duration' => null],
        };
    }
}
