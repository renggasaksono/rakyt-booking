<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the admin's cross-organization booking search (F-013,
     * ADR-0041). Every existing bookings / trip_departures index leads with
     * organization_id, so a search without an organization would scan the
     * whole table: the exact reference and E.164 phone lookups, the
     * newest-first list and the departure-date filter.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('reference');
            $table->index('customer_phone');
            $table->index(['created_at', 'id']);
        });

        Schema::table('trip_departures', function (Blueprint $table) {
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['reference']);
            $table->dropIndex(['customer_phone']);
            $table->dropIndex(['created_at', 'id']);
        });

        Schema::table('trip_departures', function (Blueprint $table) {
            $table->dropIndex(['start_date']);
        });
    }
};
