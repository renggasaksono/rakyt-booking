<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the admin's cross-organization reads (F-015). Both existing
     * indexes lead with organization_id, so the newest-first views without an
     * organization (all, by actor, by action, platform-level) and events about
     * a user (no fixed organization) would scan the whole table.
     */
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->index(['created_at', 'id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'id']);
            $table->dropIndex(['subject_type', 'subject_id']);
        });
    }
};
