<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToOrganization;
use App\Trips\BookingMode;
use App\Trips\Duration;
use App\Trips\TripStatus;
use App\Trips\TripType;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable offering (ADR-0005). Write through App\Trips\TripService.
 */
#[Fillable([
    'type', 'booking_mode', 'name', 'short_description',
    'duration_label', 'duration_days', 'duration_nights',
    'inclusions', 'exclusions', 'meeting_point', 'meeting_time', 'map_url',
    'itinerary', 'terms', 'base_price',
])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $attributes = [
        'status' => 'draft',
        'inclusions' => '[]',
        'exclusions' => '[]',
        'itinerary' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'type' => TripType::class,
            'booking_mode' => BookingMode::class,
            'status' => TripStatus::class,
            'published_at' => 'datetime',
            'duration_days' => 'integer',
            'duration_nights' => 'integer',
            'inclusions' => 'array',
            'exclusions' => 'array',
            'itinerary' => 'array',
            'base_price' => 'integer',
        ];
    }

    /**
     * H:i, or null (PostgreSQL returns H:i:s).
     */
    protected function meetingTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : substr($value, 0, 5),
        );
    }

    public function duration(): ?Duration
    {
        return Duration::fromColumns($this->duration_label, $this->duration_days, $this->duration_nights);
    }

    public function isPublished(): bool
    {
        return $this->status === TripStatus::Published;
    }

    public function isScheduled(): bool
    {
        return $this->booking_mode === BookingMode::Scheduled;
    }

    /**
     * @return HasMany<TripPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TripPhoto::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<TripDeparture, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(TripDeparture::class);
    }
}
