<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer bookings (F-005 Data & rules, ADR-0026, ADR-0027). Seats are
     * counted on trip_departures.seats_taken; the invariant is
     * seats_taken = Σ participants of awaiting_payment, needs_review and paid bookings.
     */
    public function up(): void
    {
        // Target of the composite foreign key below (same pattern as trips).
        Schema::table('trip_departures', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('trip_departure_id');
            $table->string('reference', 24);
            // ADR-0027 §2: lookup by SHA-256; the encrypted copy lets the seller resend the link.
            $table->char('access_token_hash', 64)->unique();
            $table->text('access_token');
            $table->uuid('submission_key');
            $table->string('customer_name', 80);
            $table->string('customer_phone', 16);
            $table->smallInteger('participants');
            $table->text('notes')->nullable();
            $table->integer('unit_price');
            $table->integer('total_amount');
            $table->string('status', 20);
            $table->string('cancel_reason', 20)->nullable();
            $table->timestampTz('hold_expires_at');
            $table->timestampsTz();

            // A departure with bookings can never be deleted (F-003 AC-16, AC-29).
            $table->foreign(['organization_id', 'trip_departure_id'])->references(['organization_id', 'id'])->on('trip_departures')->restrictOnDelete();
            $table->unique(['organization_id', 'reference']);
            $table->unique(['organization_id', 'submission_key']);
            $table->index(['organization_id', 'trip_departure_id', 'status']);
            $table->index(['organization_id', 'created_at']);
            // The expiry job scans awaiting_payment holds by expiry across organizations.
            $table->index(['status', 'hold_expires_at']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_participants_check CHECK (participants >= 1)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_unit_price_check CHECK (unit_price BETWEEN 1000 AND 100000000)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_total_amount_check CHECK (total_amount = participants * unit_price)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('awaiting_payment', 'needs_review', 'paid', 'rejected', 'cancelled', 'completed'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_cancel_reason_check CHECK ((status = 'cancelled') = (cancel_reason IS NOT NULL) AND (cancel_reason IS NULL OR cancel_reason IN ('hold_expired', 'seller')))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');

        Schema::table('trip_departures', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
