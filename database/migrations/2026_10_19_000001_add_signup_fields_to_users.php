<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F-001 A3: sign-up timestamps; emails unique case-insensitively (A2).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            // Start of the "time to first published trip" metric (D4, blueprint §9).
            $table->timestamp('signup_started_at')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'signup_started_at']);
        });
    }
};
