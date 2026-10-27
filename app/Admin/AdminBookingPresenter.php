<?php

namespace App\Admin;

use App\Admin\Queries\AdminBookingQueries;
use App\Bookings\BookingPresenter;
use App\Bookings\BookingStatus;
use App\Bookings\SellerBookingPresenter;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\PaymentRecord;
use App\Organizations\OrganizationStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The admin booking search rows and booking detail as Inertia props (F-013).
 * Every field that leaves the server is listed here — never a model's
 * toArray(): no numeric id, access token, customer link, submission key or
 * evidence path. The customer's phone is on the detail only (AC-5). Created
 * and hold times are in WIB, the admin's zone, with the zone in the label;
 * departure dates are the seller's local dates.
 */
class AdminBookingPresenter
{
    public function __construct(
        private AdminBookingQueries $queries,
        private AuditEventPresenter $auditEvents,
    ) {}

    /**
     * A search result, from AdminBookingQueries::search()'s joined row.
     *
     * @param  array<string, string>  $listQuery
     * @return array<string, mixed>
     */
    public function row(Booking $booking, CarbonInterface $now, array $listQuery): array
    {
        $status = OrganizationStatus::from((string) $booking->getAttribute('organization_status'));
        $today = CarbonImmutable::instance($now)->setTimezone((string) $booking->getAttribute('organization_timezone'))->toDateString();

        return [
            'id' => $booking->public_id,
            'reference' => $booking->reference,
            'customer_name' => $booking->customer_name,
            'participants' => $booking->participants,
            'total_amount' => $booking->total_amount,
            'total_label' => SellerBookingPresenter::rupiah($booking->total_amount),
            'status' => $booking->status->value,
            'status_label' => __('booking.status.'.$booking->status->value),
            'trip_name' => $booking->getAttribute('trip_name'),
            'departure_label' => BookingPresenter::dates(
                substr((string) $booking->getAttribute('departure_start_date'), 0, 10),
                substr((string) $booking->getAttribute('departure_end_date'), 0, 10),
                $today,
            ),
            'created_at' => self::iso($booking->created_at),
            'created_label' => self::dateTime($booking->created_at),
            'organization' => [
                'id' => $booking->organization_id,
                'name' => $booking->getAttribute('organization_name'),
                'slug' => $booking->getAttribute('organization_slug'),
                'status' => $status->value,
                'status_label' => $status->label(),
                'url' => route('admin.sellers.show', $booking->organization_id),
            ],
            'url' => route('admin.bookings.show', [$booking->public_id, ...$listQuery]),
        ];
    }

    /**
     * The read-only detail (AC-4): seller, trip, departure with seats,
     * customer, amounts, payment and evidence, hold expiry and audit history.
     *
     * @param  iterable<AuditEvent>  $auditEvents
     * @return array<string, mixed>
     */
    public function detail(Booking $booking, iterable $auditEvents, CarbonInterface $now): array
    {
        $organization = $this->queries->organizationOf($booking);
        $departure = $this->queries->departureOf($organization, $booking);
        $record = $this->queries->paymentRecordOf($organization, $booking);
        $today = CarbonImmutable::instance($now)->setTimezone($organization->timezone)->toDateString();

        return [
            'booking' => [
                'id' => $booking->public_id,
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'status_label' => __('booking.status.'.$booking->status->value),
                'cancel_reason_label' => $booking->cancel_reason === null
                    ? null
                    : __('admin.bookings.cancel_reasons.'.$booking->cancel_reason->value),
                'customer_name' => $booking->customer_name,
                // AC-5: the only place the admin sees a customer's phone.
                'customer_phone' => $booking->customer_phone,
                'participants' => $booking->participants,
                'unit_price' => $booking->unit_price,
                'unit_price_label' => SellerBookingPresenter::rupiah($booking->unit_price),
                'total_amount' => $booking->total_amount,
                'total_label' => SellerBookingPresenter::rupiah($booking->total_amount),
                'created_at' => self::iso($booking->created_at),
                'created_label' => self::dateTime($booking->created_at),
                'hold' => $booking->status === BookingStatus::AwaitingPayment ? [
                    'expires_at' => self::iso($booking->hold_expires_at),
                    'expires_label' => self::dateTime($booking->hold_expires_at),
                    'active' => $booking->holdIsActive($now),
                ] : null,
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'status_label' => $organization->status->label(),
                'url' => route('admin.sellers.show', $organization->id),
            ],
            'trip' => [
                'name' => $departure->trip->name,
            ],
            'departure' => [
                'start_date' => $departure->start_date->toDateString(),
                'end_date' => $departure->end_date->toDateString(),
                'date_label' => BookingPresenter::dates($departure->start_date->toDateString(), $departure->end_date->toDateString(), $today),
                'status' => $departure->status->value,
                'status_label' => __('admin.sellers.departure_statuses.'.$departure->status->value),
                'seats' => $this->queries->seats($organization, $departure),
            ],
            'payment' => $this->payment($organization, $booking, $record),
            'audit_events' => $this->auditEvents->rows($auditEvents),
        ];
    }

    /**
     * The payment record behind the booking (F-006). Evidence opens through
     * F-012's short-lived signed admin route — never a storage path.
     *
     * @return array<string, mixed>
     */
    private function payment(Organization $organization, Booking $booking, ?PaymentRecord $record): array
    {
        $amountDue = $record?->amount_due ?? $booking->total_amount;

        return [
            'amount_due' => $amountDue,
            'amount_due_label' => SellerBookingPresenter::rupiah($amountDue),
            'evidence_url' => $record?->hasEvidence()
                ? route('admin.sellers.evidence', [$organization->id, $booking->public_id])
                : null,
            'uploaded_at' => self::iso($record?->evidence_uploaded_at),
            'uploaded_label' => $record?->evidence_uploaded_at === null ? null : self::dateTime($record->evidence_uploaded_at),
            'decision' => $record?->decision?->value,
            'decision_label' => $record?->decision === null ? null : __('admin.bookings.decisions.'.$record->decision->value),
            'verified_at' => self::iso($record?->verified_at),
            'verified_label' => $record?->verified_at === null ? null : self::dateTime($record->verified_at),
            'reject_reason' => $record?->reject_reason,
        ];
    }

    private static function dateTime(CarbonInterface $at): string
    {
        $local = CarbonImmutable::instance($at)->setTimezone(AuditEventPresenter::TIMEZONE);

        return __('admin.audit.at', ['date' => $local->translatedFormat('j M Y'), 'time' => $local->format('H.i')]);
    }

    private static function iso(?CarbonInterface $at): ?string
    {
        return $at === null ? null : CarbonImmutable::instance($at)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
