<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform-admin flag and TOTP two-factor enrolment (F-010, ADR-0011).
     * The flag is set only by the admin:grant/admin:revoke commands, never over
     * HTTP (it is not mass-assignable on the User model).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
            // Encrypted at the model boundary; enrolled when the flag is granted.
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_platform_admin', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
