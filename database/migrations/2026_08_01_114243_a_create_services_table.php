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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('date');
            $table->string('ref_no')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('payor');
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->string('si_no')->nullable();
            $table->enum('service_payment_type', ['credit', 'immediate'])->default('credit');
            $table->decimal('amount_w_vat', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('net_purchases', 14, 2)->default(0);
            $table->decimal('vat_exempt', 14, 2)->default(0);
            $table->decimal('non_vat_purchase', 14, 2)->default(0);
            $table->decimal('total_purchases', 14, 2)->storedAs('net_purchases + vat_exempt + non_vat_purchase');
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
