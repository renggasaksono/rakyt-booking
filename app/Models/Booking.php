<?php

namespace App\Models;

use App\Bookings\BookingStatus;
use App\Bookings\CancelReason;
use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A customer's booking of seats on one departure (F-005, ADR-0026). Write
 * through App\Bookings\BookingService: it moves seats, the status and the
 * audit trail together under the departure lock.
 *
 * Price, reference, token, status and hold are never mass-assignable; the
 * hold is set once at creation and never extended (AC-28, ADR-0006).
 */
#[Fillable(['trip_departure_id', 'customer_name', 'customer_phone', 'participants', 'notes'])]
#[Hidden(['access_token', 'access_token_hash', 'submission_key'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'participants' => 'integer',
            'unit_price' => 'integer',
            'total_amount' => 'integer',
            'status' => BookingStatus::class,
            'cancel_reason' => CancelReason::class,
            'hold_expires_at' => 'immutable_datetime',
            'access_token' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // AC-28: no route, job or service extends a hold.
        static::updating(function (Booking $booking) {
            if ($booking->isDirty('hold_expires_at')) {
                throw new LogicException('A booking\'s hold is set once at creation (ADR-0006).');
            }
        });
    }

    /**
     * @return BelongsTo<TripDeparture, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(TripDeparture::class, 'trip_departure_id');
    }

    /**
     * The one "hold active" rule (AC-27, ADR-0026 §7): awaiting payment and
     * before the hold's end. An expired hold is inactive even before the
     * expiry job runs. F-006's evidence upload uses it.
     */
    public function holdIsActive(?CarbonInterface $now = null): bool
    {
        return $this->status === BookingStatus::AwaitingPayment
            && ($now ?? CarbonImmutable::now())->lessThan($this->hold_expires_at);
    }

    /**
     * Awaiting payment with the hold's end reached: due for release.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeExpiredHolds(Builder $query, CarbonInterface $now): void
    {
        // Bindings are written without an offset, so compare in UTC (ADR-0014).
        $query->where('status', BookingStatus::AwaitingPayment)
            ->where('hold_expires_at', '<=', CarbonImmutable::instance($now)->utc());
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
