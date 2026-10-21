<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use App\Organizations\BookingSlug;
use App\WhatsApp\WhatsAppLink;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Seller-side booking props: Hari ini rows, the list and the detail (F-007
 * AC-36, Data & rules → Props). Every field that leaves the server is listed
 * here — never a model's toArray(); no numeric id, organization_id, token
 * hash or submission key. The access token appears only in the detail's
 * customer link and WhatsApp reminder (AC-24, ADR-0029 §1). Bookings come with
 * `departure.trip` loaded.
 */
final class SellerBookingPresenter
{
    /**
     * The booking item of Hari ini and the list. `$listQuery` carries the
     * list's filters into the detail link, so its "←" restores them (AC-19).
     * Hari ini rows also get `whatsapp_reminder_url`: a seller route that
     * redirects to WhatsApp, so the token stays out of Hari ini (ADR-0029 §1).
     *
     * @param  array<string, string>  $listQuery
     * @return array<string, mixed>
     */
    public function item(Booking $booking, CarbonInterface $now, array $listQuery = [], bool $withReminder = false): array
    {
        $departure = $booking->departure;

        $item = [
            'public_id' => $booking->public_id,
            'reference' => $booking->reference,
            'customer_name' => $booking->customer_name,
            'participants' => $booking->participants,
            'total_amount' => $booking->total_amount,
            'status' => $booking->status->value,
            'hold_expires_at' => self::iso($booking->hold_expires_at),
            'created_at' => self::iso($booking->created_at),
            'trip' => ['name' => $departure->trip->name],
            'departure' => [
                'start_date' => $departure->start_date->toDateString(),
                'end_date' => $departure->end_date->toDateString(),
            ],
            'urls' => ['show' => route('bookings.show', [$booking, ...$listQuery])],
        ];

        if ($withReminder) {
            $item['whatsapp_reminder_url'] = $booking->holdIsActive($now) ? route('bookings.remind', $booking) : null;
        }

        // F-006 AC-16: needs_review rows carry the gated evidence thumbnail and
        // the inline Konfirmasi / Tolak targets. All use the public id, so the
        // customer token never enters Hari ini's props (ADR-0029 §1).
        if ($booking->status === BookingStatus::NeedsReview) {
            $item['payment'] = ['evidence_url' => route('bookings.evidence', $booking)];
            $item['urls']['confirm'] = route('bookings.confirm', [$booking, ...$listQuery]);
            $item['urls']['reject'] = route('bookings.reject', [$booking, ...$listQuery]);
        }

        return $item;
    }

    /**
     * The detail page (AC-23–AC-29).
     *
     * @param  array<string, string>  $listQuery
     * @return array<string, mixed>
     */
    public function detail(Organization $organization, Booking $booking, array $listQuery): array
    {
        $now = CarbonImmutable::now();
        $trip = $booking->departure->trip;
        $record = PaymentRecord::query()->where('booking_id', $booking->getKey())->first();

        return [
            'booking' => [
                ...$this->item($booking, $now, $listQuery),
                'customer_phone' => $booking->customer_phone,
                'notes' => $booking->notes,
                'unit_price' => $booking->unit_price,
                'cancel_reason' => $booking->cancel_reason?->value,
                'customer_url' => $this->customerUrl($organization, $booking),
                'can_cancel' => $booking->status->holdsSeats(),
                // F-006: verify inline from the detail (AC-16–AC-18).
                'can_verify' => $booking->status === BookingStatus::NeedsReview,
                'payment' => self::payment($booking, $record),
            ],
            'trip' => [
                'name' => $trip->name,
                'edit_url' => route('trips.edit', $trip),
            ],
            'meeting' => ['point' => $trip->meeting_point, 'time' => $trip->meeting_time],
            'whatsapp' => [
                'chat_url' => self::whatsappBase($booking),
                'reminder_url' => $booking->holdIsActive($now) ? $this->reminderUrl($organization, $booking, $now) : null,
                'cancelled_url' => $booking->cancel_reason === CancelReason::Seller ? $this->cancelledUrl($organization, $booking, $now) : null,
                // AC-18: "Kabari pelanggan via WA" after a rejection.
                'rejected_url' => $booking->status === BookingStatus::Rejected ? $this->rejectedUrl($organization, $booking, $now) : null,
            ],
            'context' => [
                'now' => self::iso($now),
                'timezone' => $organization->timezone,
                'today' => TripDeparture::today($organization, $now),
            ],
            'copy' => [
                ...__('bookings'),
                'status_words' => __('booking.status'),
                'calendar' => __('trips.calendar'),
            ],
            'urls' => [
                'back' => route('bookings.index', $listQuery),
                'cancel' => route('bookings.cancel', [$booking, ...$listQuery]),
                'confirm' => route('bookings.confirm', [$booking, ...$listQuery]),
                'reject' => route('bookings.reject', [$booking, ...$listQuery]),
            ],
        ];
    }

    /**
     * The payment record behind the booking for the detail (AC-16, AC-18). The
     * evidence is a gated seller URL (public id, no token, no storage path).
     *
     * @return array<string, mixed>
     */
    private static function payment(Booking $booking, ?PaymentRecord $record): array
    {
        return [
            'amount_due' => $record?->amount_due ?? $booking->total_amount,
            'has_evidence' => $record?->hasEvidence() ?? false,
            'evidence_url' => ($record !== null && $record->hasEvidence()) ? route('bookings.evidence', $booking) : null,
            'uploaded_at' => self::iso($record?->evidence_uploaded_at),
            'decision' => $record?->decision?->value,
            'reject_reason' => $record?->reject_reason,
            'verified_at' => self::iso($record?->verified_at),
        ];
    }

    /**
     * F-006 AC-18 / F-008 AC-B8: wa.me/<customer digits>?text=<whatsapp.rejected>;
     * null when the customer number is invalid (AC-B5, section-B edge case).
     */
    public function rejectedUrl(Organization $organization, Booking $booking, CarbonInterface $now): ?string
    {
        return WhatsAppLink::toNumber($booking->customer_phone, __('whatsapp.rejected', [
            'name' => $booking->customer_name,
            'reference' => $booking->reference,
        ]));
    }

    /**
     * F-007 AC-25 / F-008 AC-B6: wa.me/<customer digits>?text=<whatsapp.reminder>,
     * with the customer link; null when the customer number is invalid (AC-B5).
     */
    public function reminderUrl(Organization $organization, Booking $booking, CarbonInterface $now): ?string
    {
        $timezone = $organization->timezone;
        $release = CarbonImmutable::instance($booking->hold_expires_at)->setTimezone($timezone);
        $time = $release->format('H.i');

        if ($release->toDateString() > TripDeparture::today($organization, $now)) {
            $time = __('whatsapp.tomorrow', ['time' => $time]);
        }

        return WhatsAppLink::toNumber($booking->customer_phone, __('whatsapp.reminder', [
            'name' => $booking->customer_name,
            'reference' => $booking->reference,
            'trip' => $booking->departure->trip->name,
            'date' => $this->dates($organization, $booking, $now),
            'n' => $booking->participants,
            'total' => self::rupiah($booking->total_amount),
            'time' => $time,
            'url' => $this->customerUrl($organization, $booking) ?? '',
        ]));
    }

    /**
     * F-007 AC-29 / F-008 AC-B7: "Kabari pelanggan via WA" after the seller
     * cancelled; null when the customer number is invalid (AC-B5).
     */
    public function cancelledUrl(Organization $organization, Booking $booking, CarbonInterface $now): ?string
    {
        return WhatsAppLink::toNumber($booking->customer_phone, __('whatsapp.cancelled', [
            'name' => $booking->customer_name,
            'reference' => $booking->reference,
            'trip' => $booking->departure->trip->name,
            'date' => $this->dates($organization, $booking, $now),
        ]));
    }

    /**
     * AC-24: https://rakyt.id/<slug>/pesanan/<token>, from the encrypted copy (ADR-0027 §2).
     */
    public function customerUrl(Organization $organization, Booking $booking): ?string
    {
        $token = $booking->access_token;

        return is_string($token) && $token !== '' ? BookingSlug::url($organization->slug).'/pesanan/'.$token : null;
    }

    /**
     * "Rp2.500.000"
     */
    public static function rupiah(int $amount): string
    {
        return __('bookings.currency').number_format($amount, 0, ',', '.');
    }

    private function dates(Organization $organization, Booking $booking, CarbonInterface $now): string
    {
        return BookingPresenter::dates(
            $booking->departure->start_date->toDateString(),
            $booking->departure->end_date->toDateString(),
            TripDeparture::today($organization, $now),
        );
    }

    /**
     * AC-25: "Chat WhatsApp" — https://wa.me/<customer digits>, no text; null
     * when the customer number is invalid (AC-B5, section-B edge case).
     */
    private static function whatsappBase(Booking $booking): ?string
    {
        return WhatsAppLink::chatNumber($booking->customer_phone);
    }

    private static function iso(?CarbonInterface $time): ?string
    {
        return $time === null ? null : CarbonImmutable::instance($time)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
