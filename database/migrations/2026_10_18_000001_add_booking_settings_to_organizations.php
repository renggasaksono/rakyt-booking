<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The organization fields F-003 reads (Build order, F-003 before F-001).
     * F-001 adds the profile fields and the time-zone field in Pengaturan.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // ADR-0014: one of the three Indonesian zones.
            $table->string('timezone', 32)->default('Asia/Jakarta');
            // F-003 Q6: H-3 by default (mockups 3a, 4d).
            $table->string('default_booking_deadline_preset', 16)->default('h3');
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_timezone_check CHECK (timezone IN ('Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'))");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_default_booking_deadline_preset_check CHECK (default_booking_deadline_preset IN ('h1', 'h3', 'h7'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_timezone_check');
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_default_booking_deadline_preset_check');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'default_booking_deadline_preset']);
        });
    }
};
