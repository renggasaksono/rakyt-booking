<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Payments\PaymentDecision;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\PaymentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The payment detail behind a booking (F-006, ADR-0030 §1): amount due, uploaded
 * evidence and the seller's verification. Write through App\Bookings\BookingService
 * so the record, bookings.status and seats move in one transaction under the lock.
 *
 * evidence_path is a private-disk path — never exposed in props, logs or audit
 * (AC-19, AC-30), so it is hidden from array/JSON serialisation.
 */
#[Fillable(['amount_due', 'evidence_path', 'evidence_uploaded_at', 'verified_by', 'verified_at', 'decision', 'reject_reason'])]
#[Hidden(['evidence_path'])]
class PaymentRecord extends Model
{
    /** @use HasFactory<PaymentRecordFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'amount_due' => 'integer',
            'evidence_uploaded_at' => 'immutable_datetime',
            'verified_by' => 'integer',
            'verified_at' => 'immutable_datetime',
            'decision' => PaymentDecision::class,
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function hasEvidence(): bool
    {
        return $this->evidence_path !== null;
    }
}
