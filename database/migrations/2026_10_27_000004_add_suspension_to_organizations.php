<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who suspended an organization, when and why (F-014). Set together with
     * `status = suspended` by the platform admin and cleared on reactivation;
     * the check keeps the columns and the status in step.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspended_reason', 500)->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE organizations ADD CONSTRAINT organizations_suspension_check CHECK (
                (status = 'active' AND suspended_at IS NULL AND suspended_by IS NULL AND suspended_reason IS NULL)
                OR (status = 'suspended' AND suspended_at IS NOT NULL AND suspended_reason IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_suspension_check');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspended_reason']);
        });
    }
};
