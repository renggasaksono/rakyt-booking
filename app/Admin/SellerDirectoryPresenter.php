<?php

namespace App\Admin;

use App\Admin\Queries\SellerDirectoryQueries;
use App\Bookings\BookingPresenter;
use App\Bookings\SellerBookingPresenter;
use App\Dashboard\SeatBreakdown;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Organizations\BookingSlug;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The admin seller directory's rows and organization detail as Inertia props
 * (F-012). Read-only; every read goes through SellerDirectoryQueries. Times
 * are shown in WIB, the admin's zone (as the audit view); departure dates
 * are the seller's local dates.
 */
class SellerDirectoryPresenter
{
    public const TRIPS_LIMIT = 50;

    public const DEPARTURES_LIMIT = 20;

    public const BOOKINGS_LIMIT = 10;

    public function __construct(
        private SellerDirectoryQueries $queries,
        private AuditEventPresenter $auditEvents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function row(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'whatsapp' => $organization->whatsapp,
            'city' => $organization->city,
            'status' => $organization->status->value,
            'status_label' => $organization->status->label(),
            'registered_label' => $this->date($organization->created_at),
            'url' => route('admin.sellers.show', $organization->id),
        ];
    }

    /**
     * @param  iterable<AuditEvent>  $auditEvents
     * @return array<string, mixed>
     */
    public function detail(Organization $organization, iterable $auditEvents): array
    {
        $now = CarbonImmutable::now();
        $members = $this->queries->members($organization);
        $owner = $members->firstWhere('role', OrganizationMember::ROLE_OWNER)?->user;

        return [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'public_url' => BookingSlug::url($organization->slug),
                'booking_code' => $organization->booking_code,
                'whatsapp' => $organization->whatsapp,
                'city' => $organization->city,
                'bio' => $organization->bio,
                'status' => $organization->status->value,
                'status_label' => $organization->status->label(),
                'plan' => $organization->plan,
                'plan_label' => $this->label('admin.sellers.plans.'.$organization->plan, (string) $organization->plan),
                'registered_label' => $this->date($organization->created_at),
            ],
            // F-014: who suspended it, when and why; null while active.
            'suspension' => $organization->isSuspended() ? [
                'at' => $this->iso($organization->suspended_at),
                'at_label' => $this->dateTime($organization->suspended_at),
                'by' => $this->queries->suspendedByName($organization) ?? __('admin.sellers.suspension.unknown_admin'),
                'reason' => $organization->suspended_reason,
            ] : null,
            'last_activity' => [
                'at' => $owner?->last_login_at === null ? null : $this->iso($owner->last_login_at),
                'label' => $this->lastLogin($owner?->last_login_at),
            ],
            'members' => $members->map(fn (OrganizationMember $member) => [
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role,
                'role_label' => $this->label('admin.sellers.roles.'.$member->role, $member->role),
                'last_login_label' => $this->lastLogin($member->user->last_login_at),
            ])->values()->all(),
            'trips' => $this->trips($organization),
            'departures' => $this->departures($organization, $now),
            'bookings' => $this->bookings($organization, $now),
            'audit_events' => $this->auditEvents->rows($auditEvents),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, more: bool}
     */
    private function trips(Organization $organization): array
    {
        $trips = $this->queries->trips($organization, self::TRIPS_LIMIT + 1);

        return [
            'items' => $trips->take(self::TRIPS_LIMIT)->map(fn (Trip $trip) => [
                'id' => $trip->public_id,
                'name' => $trip->name,
                'status' => $trip->status->value,
                'status_label' => __('admin.sellers.trip_statuses.'.$trip->status->value),
                'departures_count' => (int) $trip->getAttribute('departures_count'),
            ])->values()->all(),
            'more' => $trips->count() > self::TRIPS_LIMIT,
        ];
    }

    /**
     * F-012 AC-4: upcoming departures with the seller dashboard's seat summary.
     *
     * @return array{items: list<array<string, mixed>>, more: bool}
     */
    private function departures(Organization $organization, CarbonInterface $now): array
    {
        $today = $this->queries->today($organization, $now);
        $upcoming = $this->queries->upcomingDepartures($organization, $today, self::DEPARTURES_LIMIT + 1);
        $departures = $upcoming->take(self::DEPARTURES_LIMIT);
        $sums = $this->queries->seatSums($organization, $departures);

        return [
            'items' => $departures->map(fn (TripDeparture $departure) => [
                'id' => $departure->public_id,
                'trip_name' => $departure->trip->name,
                'date_label' => $this->dateRange($departure, $today),
                'status' => $departure->status->value,
                'status_label' => __('admin.sellers.departure_statuses.'.$departure->status->value),
                'seats' => SeatBreakdown::seats($departure, $sums),
            ])->values()->all(),
            'more' => $upcoming->count() > self::DEPARTURES_LIMIT,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bookings(Organization $organization, CarbonInterface $now): array
    {
        $today = $this->queries->today($organization, $now);
        $bookings = $this->queries->recentBookings($organization, self::BOOKINGS_LIMIT);
        $withEvidence = $this->queries->bookingsWithEvidence($organization, $bookings);

        return $bookings->map(fn (Booking $booking) => [
            'reference' => $booking->reference,
            'customer_name' => $booking->customer_name,
            'trip_name' => $booking->departure->trip->name,
            'departure_label' => $this->dateRange($booking->departure, $today),
            'participants' => $booking->participants,
            'total_label' => SellerBookingPresenter::rupiah($booking->total_amount),
            'status' => $booking->status->value,
            'status_label' => __('booking.status.'.$booking->status->value),
            'created_label' => $this->dateTime($booking->created_at),
            'evidence_url' => in_array($booking->id, $withEvidence, true)
                ? route('admin.sellers.evidence', [$organization->id, $booking->public_id])
                : null,
        ])->values()->all();
    }

    /**
     * "24–26 Okt" as in the seller app; the year only when it is not the
     * organization's current one.
     */
    private function dateRange(TripDeparture $departure, string $today): string
    {
        return BookingPresenter::dates($departure->start_date->toDateString(), $departure->end_date->toDateString(), $today);
    }

    private function lastLogin(?CarbonInterface $at): string
    {
        return $at === null ? __('admin.sellers.never_signed_in') : $this->dateTime($at);
    }

    private function date(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->setTimezone(AuditEventPresenter::TIMEZONE)->translatedFormat('j M Y');
    }

    private function dateTime(CarbonInterface $at): string
    {
        $local = CarbonImmutable::instance($at)->setTimezone(AuditEventPresenter::TIMEZONE);

        return __('admin.audit.at', ['date' => $local->translatedFormat('j M Y'), 'time' => $local->format('H.i')]);
    }

    private function iso(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function label(string $key, string $fallback): string
    {
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $fallback;
    }
}
