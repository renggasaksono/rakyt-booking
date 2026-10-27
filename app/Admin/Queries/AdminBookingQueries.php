<?php

namespace App\Admin\Queries;

use App\Admin\AdminBookingFilters;
use App\Bookings\BookingFilters;
use App\Dashboard\SeatBreakdown;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;

/**
 * Cross-organization reads of the admin booking search and booking detail
 * (F-013, ADR-0011, ADR-0041). Read-only.
 *
 * The search is the one admin read that spans organizations by design: one
 * query joins each booking to its own departure, trip and organization on
 * the composite `(organization_id, id)` keys, so a row can never pair a
 * booking with another seller's departure or trip. The detail reads one
 * organization's rows only, naming the organization in every query (as
 * SellerDirectoryQueries, ADR-0039).
 */
class AdminBookingQueries
{
    private const PUBLIC_ID_PATTERN = '/^[0-9a-z]{26}$/';

    /** The list row: booking columns, then its departure, trip and organization. */
    private const ROW_COLUMNS = [
        'bookings.id',
        'bookings.public_id',
        'bookings.organization_id',
        'bookings.reference',
        'bookings.customer_name',
        'bookings.participants',
        'bookings.total_amount',
        'bookings.status',
        'bookings.created_at',
        'trip_departures.start_date as departure_start_date',
        'trip_departures.end_date as departure_end_date',
        'trips.name as trip_name',
        'organizations.name as organization_name',
        'organizations.slug as organization_slug',
        'organizations.status as organization_status',
        'organizations.timezone as organization_timezone',
    ];

    /**
     * One page of the search, newest booking first. Ask for one more than a
     * page to know whether a next page exists. Never selects the customer's
     * phone (AC-5).
     *
     * @return Collection<int, Booking>
     */
    public function search(AdminBookingFilters $filters, int $offset, int $limit): Collection
    {
        return $this->apply($filters, $this->rows())
            // The `(created_at, id)` index serves the unfiltered list.
            ->orderByDesc('bookings.created_at')
            ->orderByDesc('bookings.id')
            ->offset($offset)
            ->limit($limit)
            ->get(self::ROW_COLUMNS);
    }

    /**
     * A booking by the public id in an admin URL, in any organization; null
     * for anything else.
     */
    public function find(mixed $publicId): ?Booking
    {
        if (! is_string($publicId) || preg_match(self::PUBLIC_ID_PATTERN, $publicId) !== 1) {
            return null;
        }

        return Booking::acrossOrganizations()->where('public_id', $publicId)->first();
    }

    public function organizationOf(Booking $booking): Organization
    {
        return Organization::query()->findOrFail($booking->organization_id);
    }

    /**
     * The booking's departure with its trip, read inside the booking's organization.
     */
    public function departureOf(Organization $organization, Booking $booking): TripDeparture
    {
        return $this->of(TripDeparture::class, $organization)
            ->with(['trip' => fn (Relation $query) => $query->withoutGlobalScope(OrganizationScope::class)
                ->where('trips.organization_id', $organization->getKey())])
            ->findOrFail($booking->trip_departure_id);
    }

    /**
     * The departure's seats as the seller dashboard counts them (F-007 AC-12).
     *
     * @return array{capacity: int, paid: int, held: int, empty: int}
     */
    public function seats(Organization $organization, TripDeparture $departure): array
    {
        return SeatBreakdown::seats(
            $departure,
            SeatBreakdown::sums($this->of(Booking::class, $organization), [$departure->getKey()]),
        );
    }

    public function paymentRecordOf(Organization $organization, Booking $booking): ?PaymentRecord
    {
        return $this->of(PaymentRecord::class, $organization)
            ->where('booking_id', $booking->getKey())
            ->first();
    }

    public function organizationExists(int $id): bool
    {
        return Organization::query()->whereKey($id)->exists();
    }

    /**
     * The organization chosen in the filter, for the picker.
     */
    public function organization(?int $id): ?Organization
    {
        return $id === null ? null : Organization::query()->find($id, ['id', 'name', 'slug']);
    }

    /**
     * Bookings joined to their own departure, trip and organization.
     *
     * @return Builder<Booking>
     */
    private function rows(): Builder
    {
        return Booking::acrossOrganizations()
            ->join('trip_departures', fn (JoinClause $join) => $join
                ->on('trip_departures.organization_id', '=', 'bookings.organization_id')
                ->on('trip_departures.id', '=', 'bookings.trip_departure_id'))
            ->join('trips', fn (JoinClause $join) => $join
                ->on('trips.organization_id', '=', 'trip_departures.organization_id')
                ->on('trips.id', '=', 'trip_departures.trip_id'))
            ->join('organizations', 'organizations.id', '=', 'bookings.organization_id');
    }

    /**
     * AC-1–AC-3: the filters combine (AND); the term matches one way.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function apply(AdminBookingFilters $filters, Builder $query): Builder
    {
        match ($filters->searchKind()) {
            AdminBookingFilters::SEARCH_REFERENCE => $query->where('bookings.reference', $filters->reference()),
            AdminBookingFilters::SEARCH_PHONE => $query->where('bookings.customer_phone', $filters->phone()),
            AdminBookingFilters::SEARCH_TEXT => $query->where(function (Builder $q) use ($filters) {
                $like = '%'.BookingFilters::escapeLike((string) $filters->search).'%';

                $q->where('bookings.customer_name', 'ilike', $like)
                    ->orWhere('organizations.name', 'ilike', $like)
                    ->orWhere('organizations.slug', 'ilike', $like);
            }),
            default => null,
        };

        return $query
            ->when($filters->status !== null, fn (Builder $q) => $q->where('bookings.status', $filters->status->value))
            ->when($filters->organization !== null, fn (Builder $q) => $q->where('bookings.organization_id', $filters->organization))
            ->when($filters->from !== null, fn (Builder $q) => $q->where('trip_departures.start_date', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $q) => $q->where('trip_departures.start_date', '<=', $filters->to));
    }

    /**
     * A seller-owned model's rows of one organization, without the tenant
     * scope (the admin has no current organization).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    private function of(string $model, Organization $organization): Builder
    {
        return $model::acrossOrganizations()
            ->where((new $model)->qualifyColumn('organization_id'), $organization->getKey());
    }
}
