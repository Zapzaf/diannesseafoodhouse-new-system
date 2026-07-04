<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('pcv_no')->unique();
            $table->foreignId('check_voucher_id')->nullable()->constrained('check_vouchers')->nullOnDelete()
                ->comment('set once this PCV batch is replenished');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('petty_cash_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_voucher_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('particulars');
            $table->foreignId('cost_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount_w_vat', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('net_purchases', 14, 2)->default(0);
            $table->decimal('vat_exempt', 14, 2)->default(0);
            $table->decimal('non_vat_purchase', 14, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_voucher_items');
        Schema::dropIfExists('petty_cash_vouchers');
    }
};
