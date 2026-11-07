<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an APV to the supplier delivery it was auto-generated from when
     * that delivery was approved. Unique so a delivery can only ever produce
     * one APV, even if approval is retried.
     */
    public function up(): void
    {
        Schema::table('purchase_vouchers', function (Blueprint $table): void {
            $table->foreignId('delivery_id')->nullable()->unique()->after('branch_id')
                ->constrained('deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_id');
        });
    }
};
