<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account status the admin seller directory shows and filters on
     * (F-012). Only F-014's suspend / reactivate changes it; F-014 adds the
     * who / when / why columns with those actions.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('status', 16)->default('active');
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('active', 'suspended'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_status_check');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('status');
        });
    }
};
