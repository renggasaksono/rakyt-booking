<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why an admin did something (F-015, ADR-0038): suspension and reactivation
     * (F-014) require a reason. Its own column, not a before/after key, so the
     * change diff stays a diff. Null for every seller and system event.
     */
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
