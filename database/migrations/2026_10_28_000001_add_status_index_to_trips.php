<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin overview counts published trips across every organization
     * (F-011, ADR-0042). The only trips status index leads with
     * organization_id, which PostgreSQL 17 cannot skip, so the count would
     * scan the table. Organizations (status, created_at) and bookings
     * (status, hold_expires_at) already have one.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
