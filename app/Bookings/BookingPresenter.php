<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Payments\PaymentMethodType;
use App\WhatsApp\WhatsAppLink;
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
        // F-014 Q1: a suspended seller's customer sees their booking record and nothing
        // that could lead to a payment or a contact — no instructions, countdown,
        // progress, WhatsApp or "Pesan lagi" (the trip page is unavailable anyway).
        $paused = $organization->isSuspended();

        return [
            'title' => __('booking.head.booking_title', ['name' => $organization->name]),
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'whatsapp_url' => $paused ? null : self::whatsappUrl($organization, $booking, $trip, $departure, $now),
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
                'url' => ! $paused && $trip->isPublished()
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
            // F-006: payment instructions, the amount, the evidence and the 3-step progress.
            // F-014 AC-6, Q1: while the seller is suspended no payment is taken — the
            // instructions never reach the page, uploads are refused, and the page says so.
            'payment' => [
                'methods' => $paused ? [] : self::methods($organization, $booking),
                'paused' => $paused,
            ],
            'amount_due' => $booking->total_amount,
            'evidence' => self::evidence($organization, $booking),
            'reject_reason' => $booking->status === BookingStatus::Rejected ? self::record($booking)?->reject_reason : null,
            'progress' => $paused ? null : self::progress($booking->status),
            'copy' => __('booking'),
            'calendar' => __('trips.calendar'),
            'urls' => [
                'home' => route('home'),
                'evidence_upload' => route('public.booking.evidence', ['organization' => $organization->slug, 'token' => $booking->access_token]),
                'evidence_show' => route('public.booking.evidence.show', ['organization' => $organization->slug, 'token' => $booking->access_token]),
                // F-016 AC-7: the page's own private URL, for the "Salin tautan pesanan"
                // button — the same builder BookingController::bookingUrl() uses for redirects.
                'self' => route('public.booking.show', ['organization' => $organization->slug, 'token' => $booking->access_token]),
            ],
        ];
    }

    /**
     * The organization's active payment methods in position order (AC-1, AC-22).
     * A bank / e-wallet carries its number and holder; QRIS carries only the
     * gated image URL (AC-22) — never a storage path.
     *
     * @return list<array<string, mixed>>
     */
    private static function methods(Organization $organization, Booking $booking): array
    {
        return PaymentMethod::query()
            ->where('active', true)
            ->orderBy('position')->orderBy('id')
            ->get()
            ->map(fn (PaymentMethod $method) => $method->type === PaymentMethodType::Qris
                ? [
                    'type' => 'qris',
                    'provider_label' => null,
                    'account_number' => null,
                    'account_holder' => null,
                    'qris_image_url' => route('public.booking.qris', ['organization' => $organization->slug, 'token' => $booking->access_token]),
                ]
                : [
                    'type' => $method->type->value,
                    'provider_label' => $method->providerLabel(),
                    'account_number' => $method->account_number,
                    'account_holder' => $method->account_holder,
                    'qris_image_url' => null,
                ])
            ->values()->all();
    }

    /**
     * The uploaded evidence as a gated URL (AC-8), or null until uploaded. The
     * storage path never leaves the server (AC-19, AC-30).
     *
     * @return ?array{thumb_url: string, uploaded_at: string}
     */
    private static function evidence(Organization $organization, Booking $booking): ?array
    {
        $record = self::record($booking);

        if ($record === null || ! $record->hasEvidence()) {
            return null;
        }

        return [
            'thumb_url' => route('public.booking.evidence.show', ['organization' => $organization->slug, 'token' => $booking->access_token]),
            'uploaded_at' => self::iso($record->evidence_uploaded_at),
        ];
    }

    private static function record(Booking $booking): ?PaymentRecord
    {
        return PaymentRecord::query()->where('booking_id', $booking->getKey())->first();
    }

    /**
     * The 3-step progress (AC-4): awaiting_payment → step 1, needs_review →
     * step 2, paid → step 3. rejected / cancelled show no progress.
     */
    private static function progress(BookingStatus $status): ?int
    {
        return match ($status) {
            BookingStatus::AwaitingPayment => 1,
            BookingStatus::NeedsReview => 2,
            BookingStatus::Paid, BookingStatus::Completed => 3,
            default => null,
        };
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
     * F-005 AC-33 / F-008 AC-B4: the booking-page "Kabari operator" link, built
     * by the shared helper; null when the org number is invalid (AC-A6/D3).
     */
    private static function whatsappUrl(Organization $organization, Booking $booking, Trip $trip, TripDeparture $departure, CarbonImmutable $now): ?string
    {
        return WhatsAppLink::to($organization, __('whatsapp.customer', [
            'seller' => $organization->name,
            'trip' => $trip->name,
            'date' => self::dates(
                $departure->start_date->toDateString(),
                $departure->end_date->toDateString(),
                TripDeparture::today($organization, $now),
            ),
            'n' => $booking->participants,
            'reference' => $booking->reference,
        ]));
    }

    private static function iso(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
