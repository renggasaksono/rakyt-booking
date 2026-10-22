<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the Day Trip trip type (F-003 Q17, 2026-10-22). Two types remain:
     * open_trip (Scheduled) and private (RequestDate). `type` drives no
     * behaviour (booking_mode + duration do), so relabeling former day-trip rows
     * to open_trip is purely cosmetic: they keep their scheduled mode and
     * duration.
     *
     * Order matters: relabel the rows first, then tighten the CHECK constraint,
     * or the ALTER would fail on any remaining day_trip row.
     */
    public function up(): void
    {
        DB::statement("UPDATE trips SET type = 'open_trip' WHERE type = 'day_trip'");

        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_type_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('open_trip', 'private'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_type_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('open_trip', 'private', 'day_trip'))");
    }
};
