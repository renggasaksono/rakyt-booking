<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QRIS as a payment method carrying an uploaded QR image (F-006, ADR-0030
     * §4). A QRIS row has no account number or holder; bank / e-wallet rows are
     * unchanged. The image lives on the private disk, shown to the customer
     * through the same gated path as evidence (AC-22).
     */
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Private-disk path for the QR image; set only for qris rows.
            $table->string('qris_image_path')->nullable()->after('account_holder');
        });

        // Bank / e-wallet rows keep their values; qris rows have neither.
        DB::statement('ALTER TABLE payment_methods ALTER COLUMN account_number DROP NOT NULL');
        DB::statement('ALTER TABLE payment_methods ALTER COLUMN account_holder DROP NOT NULL');

        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_type_check');
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (type IN ('bank', 'ewallet', 'qris'))");

        // The account-number rule applies only to bank / e-wallet rows (AC-23).
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_account_number_check');
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_account_number_check CHECK ((type = 'qris') OR (account_number ~ '^[0-9]{5,20}$'))");

        // A qris row carries an image and no account; a bank / e-wallet row the reverse.
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_qris_check CHECK ((type = 'qris') = (qris_image_path IS NOT NULL))");
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_bank_fields_check CHECK ((type = 'qris') OR (account_holder IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_bank_fields_check');
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_qris_check');
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_account_number_check');
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_account_number_check CHECK (account_number ~ '^[0-9]{5,20}$')");
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT payment_methods_type_check');
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (type IN ('bank', 'ewallet'))");

        DB::statement('ALTER TABLE payment_methods ALTER COLUMN account_holder SET NOT NULL');
        DB::statement('ALTER TABLE payment_methods ALTER COLUMN account_number SET NOT NULL');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('qris_image_path');
        });
    }
};
