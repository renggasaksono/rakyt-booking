<?php

use App\Organizations\BookingCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booking references `RKB-<booking code>-MMDD-NNNN` (F-005 AC-17, AC-17b,
     * ADR-0026 §5): the seller's code and their own booking counter. Existing
     * organizations get a code derived from their slug, in id order.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('booking_code', 4)->nullable();
            $table->unsignedInteger('last_booking_number')->default(0);
        });

        $taken = [];

        foreach (DB::table('organizations')->orderBy('id')->get(['id', 'slug']) as $organization) {
            foreach (BookingCode::candidates($organization->slug) as $code) {
                if (! isset($taken[$code])) {
                    $taken[$code] = true;
                    DB::table('organizations')->where('id', $organization->id)->update(['booking_code' => $code]);

                    break;
                }
            }
        }

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('booking_code', 4)->nullable(false)->change();
        });

        DB::statement('CREATE UNIQUE INDEX '.BookingCode::UNIQUE_INDEX.' ON organizations (booking_code)');
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_booking_code_check CHECK (booking_code ~ '^[A-Z]{3}[A-Z0-9]?$' AND booking_code <> 'RKB')");
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_last_booking_number_check CHECK (last_booking_number >= 0)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.BookingCode::UNIQUE_INDEX);
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_booking_code_check');
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_last_booking_number_check');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['booking_code', 'last_booking_number']);
        });
    }
};
