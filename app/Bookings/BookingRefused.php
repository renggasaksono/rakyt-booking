<?php

namespace App\Bookings;

use Illuminate\Validation\ValidationException;

/**
 * A submit the booking transaction refused (F-005 AC-13–AC-15, AC-19–AC-21).
 * Rendered like any validation error: back to the form, typed values kept.
 * `departure` makes the form show the "Tanggal ini sudah tidak bisa dipesan" state.
 */
final class BookingRefused extends ValidationException
{
    public static function unavailable(): self
    {
        return self::withMessages(['departure' => __('booking.form.unavailable')]);
    }

    /**
     * The seller was suspended (F-014 AC-3). The form then shows the neutral
     * "tidak tersedia" page, so the message is only a fallback.
     */
    public static function sellerUnavailable(): self
    {
        return self::withMessages(['departure' => __('public.unavailable.message')]);
    }

    public static function priceChanged(int $price): self
    {
        return self::withMessages(['price' => __('booking.errors.price_changed', ['price' => self::rupiah($price)])]);
    }

    public static function notEnoughSeats(int $seatsLeft): self
    {
        return $seatsLeft === 0
            ? self::unavailable()
            : self::withMessages(['participants' => __('booking.errors.seats_left', ['n' => $seatsLeft])]);
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
