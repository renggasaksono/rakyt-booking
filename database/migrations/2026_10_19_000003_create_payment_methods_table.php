<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where customers transfer (F-001 C10–C12; the model F-006 builds on).
     * Bank / e-wallet only for now; QRIS and cash arrive with F-006 (Q10).
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            // A key from config('rakyt.payment_providers'), or "other".
            $table->string('provider', 32);
            // The name typed for "Lainnya" / "BPD (isi nama)".
            $table->string('provider_name', 60)->nullable();
            $table->string('account_number', 20);
            $table->string('account_holder', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'active', 'position']);
        });

        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (type IN ('bank', 'ewallet'))");
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_account_number_check CHECK (account_number ~ '^[0-9]{5,20}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
