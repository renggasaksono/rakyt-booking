<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Platform-level audit events (F-010): granting or revoking platform-admin
     * access belongs to no organization, so these rows carry a null
     * organization_id. The foreign key is kept; only the NOT NULL is dropped.
     * AuditLog permits a null organization only for AuditAction::isPlatformLevel().
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE audit_events ALTER COLUMN organization_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_events ALTER COLUMN organization_id SET NOT NULL');
    }
};
