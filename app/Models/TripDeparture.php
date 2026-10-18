<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToOrganization;
use App\Trips\ClosedReason;
use App\Trips\DeadlinePreset;
use App\Trips\DepartureStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TripDepartureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated, bookable occurrence of a trip (ADR-0005). Seats change only under a
 * row lock (ADR-0006); write through App\Trips\DepartureService.
 */
#[Fillable([
    'trip_id', 'start_date', 'end_date', 'capacity', 'price_override',
    'booking_deadline_preset', 'booking_deadline_at', 'status', 'closed_reason',
])]
class TripDeparture extends Model
{
    /** @use HasFactory<TripDepartureFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $attributes = [
        'status' => 'open',
        'seats_taken' => 0,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'capacity' => 'integer',
            'seats_taken' => 'integer',
            'price_override' => 'integer',
            'booking_deadline_preset' => DeadlinePreset::class,
            'booking_deadline_at' => 'immutable_datetime',
            'status' => DepartureStatus::class,
            'closed_reason' => ClosedReason::class,
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Effective price = override ?? base price (AC-20).
     */
    public function effectivePrice(): ?int
    {
        return $this->price_override ?? $this->trip->base_price;
    }

    public function isOpen(): bool
    {
        return $this->status === DepartureStatus::Open;
    }

    public function isFull(): bool
    {
        return $this->seats_taken >= $this->capacity;
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seats_taken);
    }

    /**
     * The start date has passed in the organization's time zone (AC-26).
     */
    public function hasStarted(?CarbonInterface $now = null): bool
    {
        return $this->start_date->toDateString() < self::today($this->trip->organization, $now);
    }

    public function deadlinePassed(?CarbonInterface $now = null): bool
    {
        return ! ($now ?? CarbonImmutable::now())->lessThan($this->booking_deadline_at);
    }

    /**
     * The single server-side "bookable" rule shared with F-002 and F-005
     * (Data & rules → Bookable). Computed at request time, so it holds even if
     * the auto-close job has not run yet (AC-36).
     */
    public function isBookable(?CarbonInterface $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $trip = $this->trip;

        return $trip->isPublished()
            && $trip->isScheduled()
            && $this->isOpen()
            && ! $this->deadlinePassed($now)
            && $this->seats_taken < $this->capacity
            && ! $this->hasStarted($now);
    }

    /**
     * Query form of isBookable() for lists. The organization's "today" is passed
     * in because the scope cannot know the time zone of every row.
     *
     * @param  Builder<TripDeparture>  $query
     */
    public function scopeBookable(Builder $query, string $today, ?CarbonInterface $now = null): void
    {
        // Bindings are written without an offset, so compare in UTC (ADR-0014).
        $query->where('status', DepartureStatus::Open)
            ->where('booking_deadline_at', '>', CarbonImmutable::instance($now ?? CarbonImmutable::now())->utc())
            ->whereColumn('seats_taken', '<', 'capacity')
            ->whereDate('start_date', '>=', $today)
            ->whereHas('trip', fn (Builder $trip) => $trip
                ->where('status', 'published')
                ->where('booking_mode', 'scheduled'));
    }

    /**
     * Today's date (Y-m-d) in the organization's time zone.
     */
    public static function today(Organization $organization, ?CarbonInterface $now = null): string
    {
        return CarbonImmutable::instance($now ?? CarbonImmutable::now())->setTimezone($organization->timezone)->toDateString();
    }
}
