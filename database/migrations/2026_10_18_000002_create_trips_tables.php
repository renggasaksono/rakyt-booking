<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trips, their photos and departures (F-003, ADR-0005, ADR-0015).
     *
     * Child tables reference (organization_id, trip_id) so a photo or departure
     * can never belong to another organization's trip, even through raw SQL.
     */
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('slug', 100);
            $table->string('type', 16);
            $table->string('booking_mode', 16);
            $table->string('status', 16)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->string('name', 80);
            $table->string('short_description', 300)->nullable();
            $table->smallInteger('duration_days')->nullable();
            $table->smallInteger('duration_nights')->nullable();
            $table->string('duration_label', 8)->nullable();
            $table->jsonb('inclusions')->default('[]');
            $table->jsonb('exclusions')->default('[]');
            $table->string('meeting_point', 200)->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->jsonb('itinerary')->default('[]');
            $table->text('terms')->nullable();
            $table->integer('base_price')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'slug']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status']);
        });

        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('open_trip', 'private', 'day_trip'))");
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_booking_mode_check CHECK (booking_mode IN ('scheduled', 'request_date'))");
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check CHECK (status IN ('draft', 'published'))");
        DB::statement('ALTER TABLE trips ADD CONSTRAINT trips_base_price_check CHECK (base_price IS NULL OR base_price BETWEEN 1000 AND 100000000)');
        DB::statement(<<<'SQL'
            ALTER TABLE trips ADD CONSTRAINT trips_duration_check CHECK (
                (duration_days IS NULL AND duration_nights IS NULL AND duration_label IS NULL)
                OR (duration_label = 'hours' AND duration_days = 1 AND duration_nights = 0)
                OR (duration_label = 'days' AND duration_days BETWEEN 1 AND 30 AND duration_nights BETWEEN 0 AND duration_days)
            )
            SQL);

        Schema::create('trip_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_id');
            $table->ulid('public_id')->unique();
            $table->string('path');
            $table->jsonb('variants')->default('{}');
            $table->smallInteger('position');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'trip_id'])->references(['organization_id', 'id'])->on('trips')->cascadeOnDelete();
            $table->index(['organization_id', 'trip_id', 'position']);
        });

        Schema::create('trip_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_id');
            $table->ulid('public_id')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('capacity');
            // Maintained by F-005 (held + paid seats, ADR-0006).
            $table->integer('seats_taken')->default(0);
            $table->integer('price_override')->nullable();
            $table->string('booking_deadline_preset', 16);
            $table->timestampTz('booking_deadline_at');
            $table->string('status', 16)->default('open');
            $table->string('closed_reason', 16)->nullable();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'trip_id'])->references(['organization_id', 'id'])->on('trips')->cascadeOnDelete();
            $table->index(['organization_id', 'trip_id', 'start_date']);
            // The auto-close job scans open departures by deadline across organizations.
            $table->index(['status', 'booking_deadline_at']);
        });

        DB::statement('ALTER TABLE trip_departures ADD CONSTRAINT trip_departures_capacity_check CHECK (capacity BETWEEN 1 AND 999)');
        // ADR-0006: the database refuses overbooking whatever the application does.
        DB::statement('ALTER TABLE trip_departures ADD CONSTRAINT trip_departures_seats_taken_check CHECK (seats_taken >= 0 AND seats_taken <= capacity)');
        DB::statement('ALTER TABLE trip_departures ADD CONSTRAINT trip_departures_price_override_check CHECK (price_override IS NULL OR price_override BETWEEN 1000 AND 100000000)');
        DB::statement('ALTER TABLE trip_departures ADD CONSTRAINT trip_departures_dates_check CHECK (end_date >= start_date)');
        DB::statement("ALTER TABLE trip_departures ADD CONSTRAINT trip_departures_deadline_preset_check CHECK (booking_deadline_preset IN ('same_day', 'h1', 'h3', 'h7', 'custom'))");
        DB::statement(<<<'SQL'
            ALTER TABLE trip_departures ADD CONSTRAINT trip_departures_status_check CHECK (
                (status = 'open' AND closed_reason IS NULL)
                OR (status = 'closed' AND closed_reason IN ('manual', 'deadline'))
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_departures');
        Schema::dropIfExists('trip_photos');
        Schema::dropIfExists('trips');
    }
};
