<?php

namespace App\Admin\Queries;

use App\Admin\SellerDirectoryFilters;
use App\Dashboard\SeatBreakdown;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Tenancy\OrganizationScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Cross-organization reads of the admin seller directory (F-012, ADR-0011).
 * The directory lists every organization; the detail reads one organization's
 * members, trips, departures, bookings and payment evidence. Every
 * seller-owned read here drops the tenant scope and names the organization
 * explicitly instead — including eager loads — so each detail query stays
 * inside the one organization being viewed. Read-only; F-014's status
 * change lives in App\Organizations\OrganizationSuspension.
 */
class SellerDirectoryQueries
{
    /** Longest organization id accepted (bigint has 19 digits). */
    private const ID_DIGITS = 18;

    private const PUBLIC_ID_PATTERN = '/^[0-9a-z]{26}$/';

    /**
     * One page of the directory, newest registration first. Ask for one more
     * than a page to know whether a next page exists.
     *
     * @return Collection<int, Organization>
     */
    public function search(SellerDirectoryFilters $filters, int $offset, int $limit): Collection
    {
        return $filters->apply(Organization::query())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'whatsapp', 'city', 'status', 'created_at']);
    }

    /**
     * Organizations matching a typed term, by name (the audit view's
     * organization picker).
     *
     * @return Collection<int, Organization>
     */
    public function lookup(string $term, int $limit): Collection
    {
        return SellerDirectoryFilters::matchSearch(Organization::query(), $term)
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'slug']);
    }

    /**
     * An organization by the id in an admin URL; null for anything else.
     */
    public function find(mixed $id): ?Organization
    {
        if (! is_string($id) || ! ctype_digit($id) || strlen($id) > self::ID_DIGITS) {
            return null;
        }

        return Organization::query()->find((int) $id);
    }

    /**
     * The name of the admin who suspended the organization (F-014), if any.
     * Users are not organization-scoped.
     */
    public function suspendedByName(Organization $organization): ?string
    {
        return $organization->suspended_by === null
            ? null
            : User::query()->whereKey($organization->suspended_by)->value('name');
    }

    /**
     * Members with their users, owners first.
     *
     * @return Collection<int, OrganizationMember>
     */
    public function members(Organization $organization): Collection
    {
        return $this->of(OrganizationMember::class, $organization)
            ->with('user:id,name,email,last_login_at')
            ->orderByRaw('CASE WHEN role = ? THEN 0 ELSE 1 END', [OrganizationMember::ROLE_OWNER])
            ->orderBy('id')
            ->get();
    }

    /**
     * Trips by name, with their departure count.
     *
     * @return Collection<int, Trip>
     */
    public function trips(Organization $organization, int $limit): Collection
    {
        return $this->of(Trip::class, $organization)
            ->select(['id', 'public_id', 'organization_id', 'name', 'status', 'published_at'])
            // Naming the organization lets the count use (organization_id, trip_id, start_date).
            ->withCount(['departures' => fn (Builder $query) => $this->unscoped($query)
                ->where('trip_departures.organization_id', $organization->getKey())])
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Departures from $today on, nearest first, with their trips.
     *
     * @return Collection<int, TripDeparture>
     */
    public function upcomingDepartures(Organization $organization, string $today, int $limit): Collection
    {
        return $this->of(TripDeparture::class, $organization)
            ->with(['trip' => fn (Relation $query) => $this->unscoped($query)])
            ->whereDate('start_date', '>=', $today)
            ->orderBy('start_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * F-012 AC-4: the seller dashboard's seat sums (F-007 AC-12) for these
     * departures, read from this organization's bookings.
     *
     * @param  Collection<int, TripDeparture>  $departures
     * @return array<int, array{paid: int, held: int}>
     */
    public function seatSums(Organization $organization, Collection $departures): array
    {
        return SeatBreakdown::sums($this->of(Booking::class, $organization), $departures->modelKeys());
    }

    /**
     * The newest bookings, with their departures and trips.
     *
     * @return Collection<int, Booking>
     */
    public function recentBookings(Organization $organization, int $limit): Collection
    {
        return $this->of(Booking::class, $organization)
            ->with([
                'departure' => fn (Relation $query) => $this->unscoped($query),
                'departure.trip' => fn (Relation $query) => $this->unscoped($query),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ids of these bookings that have uploaded payment evidence.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<int>
     */
    public function bookingsWithEvidence(Organization $organization, Collection $bookings): array
    {
        if ($bookings->isEmpty()) {
            return [];
        }

        return $this->of(PaymentRecord::class, $organization)
            ->whereIn('booking_id', $bookings->modelKeys())
            ->whereNotNull('evidence_path')
            ->pluck('booking_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * F-012 AC-7: the private-disk path of a booking's payment evidence, when
     * the booking belongs to this organization and has evidence.
     */
    public function evidencePath(Organization $organization, mixed $bookingPublicId): ?string
    {
        if (! is_string($bookingPublicId) || preg_match(self::PUBLIC_ID_PATTERN, $bookingPublicId) !== 1) {
            return null;
        }

        $bookingId = $this->of(Booking::class, $organization)->where('public_id', $bookingPublicId)->value('id');

        if ($bookingId === null) {
            return null;
        }

        return $this->of(PaymentRecord::class, $organization)
            ->where('booking_id', $bookingId)
            ->whereNotNull('evidence_path')
            ->value('evidence_path');
    }

    /**
     * "Today" in the organization's timezone, for its upcoming departures.
     */
    public function today(Organization $organization, ?CarbonInterface $now = null): string
    {
        return TripDeparture::today($organization, $now);
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

    /**
     * An eager load or count of a related seller-owned model: the parent rows
     * already belong to the organization and the composite foreign keys
     * (organization_id, id) keep children in it.
     *
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function unscoped(Builder|Relation $query): Builder|Relation
    {
        return $query->withoutGlobalScope(OrganizationScope::class);
    }
}
