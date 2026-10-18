<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail (F-009 A10–A13).
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('actor_type', 32);
            $table->string('actor_id')->nullable();
            $table->string('action', 64);
            // No FK: events outlive their subject.
            $table->string('subject_type', 64);
            $table->string('subject_id', 64);
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'subject_type', 'subject_id']);
        });

        DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_actor_type_check CHECK (actor_type IN ('user', 'customer', 'system', 'rakyt_staff', 'platform_admin'))");

        // Last line of defence behind the model guard (A12): rows can be inserted, never changed.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_events_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events rows are append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_events_no_update_or_delete
                BEFORE UPDATE OR DELETE ON audit_events
                FOR EACH ROW EXECUTE FUNCTION audit_events_immutable();

            CREATE TRIGGER audit_events_no_truncate
                BEFORE TRUNCATE ON audit_events
                FOR EACH STATEMENT EXECUTE FUNCTION audit_events_immutable();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_events_immutable()');
    }
};
