<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment detail behind a booking (F-006, ADR-0030 §1): the amount due,
     * the uploaded evidence and the seller's verification. One row per booking;
     * bookings.status stays the single source of truth for the lifecycle and
     * seat accounting (ADR-0026). Rakyt never asserts "money received" — only
     * the seller's confirmation (ADR-0003).
     */
    public function up(): void
    {
        // Target of the composite foreign key below (same pattern as bookings → trip_departures).
        Schema::table('bookings', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('booking_id');
            // Snapshot of bookings.total_amount at upload; bigint like bookings.
            $table->bigInteger('amount_due');
            // Private-disk path; never public, never in props/logs/audit (AC-19, AC-30).
            $table->string('evidence_path')->nullable();
            $table->timestampTz('evidence_uploaded_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->string('decision', 12)->nullable();
            $table->string('reject_reason', 200)->nullable();
            $table->timestampsTz();

            // One record per booking, cascade when the (restricted) booking is gone.
            $table->foreign(['organization_id', 'booking_id'])->references(['organization_id', 'id'])->on('bookings')->cascadeOnDelete();
            $table->unique(['organization_id', 'booking_id']);
        });

        DB::statement('ALTER TABLE payment_records ADD CONSTRAINT payment_records_amount_due_check CHECK (amount_due >= 0)');
        DB::statement("ALTER TABLE payment_records ADD CONSTRAINT payment_records_decision_check CHECK (decision IS NULL OR decision IN ('confirmed', 'rejected'))");
        // decision is set exactly when verified_at is set (AC-17, AC-18).
        DB::statement('ALTER TABLE payment_records ADD CONSTRAINT payment_records_verified_check CHECK ((verified_at IS NULL) = (decision IS NULL))');
        // A reject reason only ever accompanies a rejection.
        DB::statement("ALTER TABLE payment_records ADD CONSTRAINT payment_records_reject_reason_check CHECK (reject_reason IS NULL OR decision = 'rejected')");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
