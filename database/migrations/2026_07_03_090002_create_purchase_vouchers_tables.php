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
        Schema::create('purchase_vouchers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('apv_no')->unique();
            $table->foreignId('vendor_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('si_no')->nullable();
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts');
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'date']);
        });

        Schema::create('purchase_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_voucher_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('particulars');
            $table->foreignId('cost_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount_w_vat', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('net_purchases', 14, 2)->default(0);
            $table->decimal('vat_exempt', 14, 2)->default(0);
            $table->decimal('non_vat_purchase', 14, 2)->default(0);
            $table->decimal('total_purchases', 14, 2)->storedAs('net_purchases + vat_exempt + non_vat_purchase');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_voucher_items');
        Schema::dropIfExists('purchase_vouchers');
    }
};
