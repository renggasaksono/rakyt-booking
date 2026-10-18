<?php

namespace App\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\Contracts\PaymentReadiness;
use Carbon\CarbonImmutable;

/**
 * "Siap terbit n/5" (F-003 AC-9–AC-11). The short description is not an item.
 */
final class PublishChecklist
{
    public const COVER_PHOTO = 'cover_photo';

    public const NAME = 'name';

    public const BASE_PRICE = 'base_price';

    public const DEPARTURE = 'departure';

    public const PAYMENT_INSTRUCTIONS = 'payment_instructions';

    public function __construct(private PaymentReadiness $payments) {}

    /**
     * Items in display order; request-date trips have no departure item (n/4).
     *
     * @return array<string, bool> item key => done
     */
    public function items(Trip $trip): array
    {
        $items = [
            self::COVER_PHOTO => $trip->photos()->exists(),
            self::NAME => trim((string) $trip->name) !== '',
            self::BASE_PRICE => $trip->base_price !== null,
        ];

        if ($trip->isScheduled()) {
            $items[self::DEPARTURE] = $this->hasUpcomingOpenDeparture($trip);
        }

        $items[self::PAYMENT_INSTRUCTIONS] = $this->payments->hasActivePaymentMethod($trip->organization);

        return $items;
    }

    /**
     * @return list<string>
     */
    public function missing(Trip $trip): array
    {
        return array_keys(array_filter($this->items($trip), fn (bool $done) => ! $done));
    }

    /**
     * AC-10: open, starts today or later, and its booking deadline is still ahead.
     */
    public function hasUpcomingOpenDeparture(Trip $trip): bool
    {
        return $trip->departures()
            ->where('status', DepartureStatus::Open)
            ->whereDate('start_date', '>=', TripDeparture::today($trip->organization))
            ->where('booking_deadline_at', '>', CarbonImmutable::now())
            ->exists();
    }

    /**
     * Labels for the given keys, for messages.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function labels(array $keys): array
    {
        return array_map(fn (string $key) => __("trips.checklist.items.{$key}"), $keys);
    }
}
