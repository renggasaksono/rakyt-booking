<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization profile (F-001 B1–B10, F1). Rows created before F-001 get
     * placeholder values so the columns can be NOT NULL.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('name', 80)->nullable();
            $table->string('slug', 30)->nullable();
            $table->string('city', 80)->nullable();
            // E.164, not verified (ADR-0012).
            $table->string('whatsapp', 16)->nullable();
            // ADR-0019: stored, not enforced in P0.
            $table->string('plan', 16)->default('free');
            $table->string('logo_path')->nullable();
            $table->string('bio', 160)->nullable();
            $table->timestamp('onboarding_skipped_at')->nullable();
            $table->timestamp('first_published_at')->nullable();
        });

        DB::statement("UPDATE organizations SET name = 'Usaha', slug = 'usaha-' || id, city = '-', whatsapp = '-' WHERE slug IS NULL");

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('name', 80)->nullable(false)->change();
            $table->string('slug', 30)->nullable(false)->change();
            $table->string('city', 80)->nullable(false)->change();
            $table->string('whatsapp', 16)->nullable(false)->change();
        });

        // B10: two sellers racing for one slug — the index decides.
        DB::statement('CREATE UNIQUE INDEX organizations_slug_lower_unique ON organizations (lower(slug))');
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_slug_format_check CHECK (slug ~ '^[a-z0-9][a-z0-9-]*[a-z0-9]$' AND position('--' in slug) = 0 AND length(slug) BETWEEN 3 AND 30)");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_plan_check CHECK (plan IN ('free'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS organizations_slug_lower_unique');
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_slug_format_check');
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_plan_check');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['name', 'slug', 'city', 'whatsapp', 'plan', 'logo_path', 'bio', 'onboarding_skipped_at', 'first_published_at']);
        });
    }
};
