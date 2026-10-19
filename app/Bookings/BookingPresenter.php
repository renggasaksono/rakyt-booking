<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Props for the booking form and the customer booking page (F-005 AC-37,
 * Data & rules → Public fields). Every field that leaves the server is
 * listed here — never a model's toArray(); no numeric id, organization_id,
 * token hash, seats_taken or other bookings. Values are ISO in code; the Vue
 * pages format them.
 */
final class BookingPresenter
{
    /**
     * Booking form (1c, AC-1–AC-9). `seats_left` and `price` only while bookable.
     *
     * @return array<string, mixed>
     */
    public function form(Organization $organization, Trip $trip, TripDeparture $departure): array
    {
        $now = CarbonImmutable::now();
        $departure->setRelation('trip', $trip);
        $trip->setRelation('organization', $organization);
        $bookable = $departure->isBookable($now);

        return [
            'title' => __('booking.head.form_title', ['trip' => $trip->name, 'name' => $organization->name]),
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'trip' => [
                'slug' => $trip->slug,
                'name' => $trip->name,
            ],
            'departure' => [
                'public_id' => $departure->public_id,
                'start_date' => $departure->start_date->toDateString(),
                'end_date' => $departure->end_date->toDateString(),
                'state' => match (true) {
                    $bookable => 'bookable',
                    $departure->isOpen() && $departure->isFull() => 'full',
                    default => 'closed',
                },
                'seats_left' => $bookable ? $departure->seatsLeft() : null,
                'price' => $bookable ? $departure->effectivePrice() : null,
            ],
            'meeting' => self::meeting($trip),
            // AC-16: a new key per form load; retries of this load reuse it.
            'submission_key' => (string) Str::uuid(),
            'hold_minutes' => BookingService::holdMinutes(),
            'context' => ['today' => TripDeparture::today($organization, $now)],
            'copy' => __('booking'),
            'calendar' => __('trips.calendar'),
            'urls' => [
                'trip' => route('public.trip', ['organization' => $organization->slug, 'trip' => $trip->slug]),
                'store' => route('public.booking.store', ['organization' => $organization->slug, 'trip' => $trip->slug]),
                'privacy' => route('legal.privacy'),
                'home' => route('home'),
            ],
        ];
    }

    /**
     * Customer booking page (1d/2e top half, AC-29–AC-37).
     *
     * @return array<string, mixed>
     */
    public function page(Organization $organization, Booking $booking): array
    {
        $now = CarbonImmutable::now();
        $departure = $booking->departure;
        $trip = $departure->trip;

        return [
            'title' => __('booking.head.booking_title', ['name' => $organization->name]),
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'whatsapp_url' => self::whatsappUrl($organization, $booking, $trip, $departure, $now),
            ],
            'booking' => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'cancel_reason' => $booking->cancel_reason?->value,
                'participants' => $booking->participants,
                'unit_price' => $booking->unit_price,
                'total_amount' => $booking->total_amount,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'hold_expires_at' => self::iso($booking->hold_expires_at),
            ],
            'trip' => [
                'name' => $trip->name,
                // AC-31: "Pesan lagi" only while the trip is still published.
                'url' => $trip->isPublished()
                    ? route('public.trip', ['organization' => $organization->slug, 'trip' => $trip->slug])
                    : null,
            ],
            'departure' => [
                'start_date' => $departure->start_date->toDateString(),
                'end_date' => $departure->end_date->toDateString(),
            ],
            'meeting' => self::meeting($trip),
            // AC-30: the countdown runs on the server's clock, not the phone's.
            'context' => [
                'now' => self::iso($now),
                'timezone' => $organization->timezone,
            ],
            'copy' => __('booking'),
            'calendar' => __('trips.calendar'),
            'urls' => ['home' => route('home')],
        ];
    }

    /**
     * "24–26 Okt", "31 Okt – 2 Nov", "24 Okt"; the year only when it is not
     * the current one. Same rule as formatDates() in resources/js/lib/publicPage.js.
     */
    public static function dates(string $start, string $end, string $today): string
    {
        $months = __('trips.calendar.months_short');
        $s = CarbonImmutable::createFromFormat('!Y-m-d', $start);
        $e = CarbonImmutable::createFromFormat('!Y-m-d', $end);
        $currentYear = (int) substr($today, 0, 4);
        $month = fn (CarbonImmutable $d) => $months[$d->month - 1];
        $year = fn (CarbonImmutable $d) => $d->year === $currentYear ? '' : ' '.$d->year;

        return match (true) {
            $start === $end => $s->day.' '.$month($s).$year($s),
            $s->year !== $e->year => "{$s->day} {$month($s)} {$s->year} – {$e->day} {$month($e)} {$e->year}",
            $s->month !== $e->month => "{$s->day} {$month($s)} – {$e->day} {$month($e)}".$year($e),
            default => "{$s->day}–{$e->day} {$month($e)}".$year($e),
        };
    }

    /**
     * @return array{point: ?string, time: ?string}
     */
    private static function meeting(Trip $trip): array
    {
        return ['point' => $trip->meeting_point, 'time' => $trip->meeting_time];
    }

    /**
     * AC-33: https://wa.me/<seller digits>?text=<booking.whatsapp.customer>.
     */
    private static function whatsappUrl(Organization $organization, Booking $booking, Trip $trip, TripDeparture $departure, CarbonImmutable $now): string
    {
        $text = __('booking.whatsapp.customer', [
            'seller' => $organization->name,
            'trip' => $trip->name,
            'date' => self::dates(
                $departure->start_date->toDateString(),
                $departure->end_date->toDateString(),
                TripDeparture::today($organization, $now),
            ),
            'n' => $booking->participants,
            'reference' => $booking->reference,
        ]);

        return 'https://wa.me/'.preg_replace('/\D+/', '', (string) $organization->whatsapp).'?text='.rawurlencode($text);
    }

    private static function iso(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
