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
        Schema::create('check_vouchers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('cv_no')->unique();
            $table->foreignId('purchase_voucher_id')->nullable()->constrained('purchase_vouchers')->nullOnDelete()
                ->comment('set when this CV pays an existing APV');
            $table->enum('type', ['pcf_replenishment', 'apv_payment', 'cod_purchase', 'other_disbursement'])
                ->default('other_disbursement');
            // draft until a Check Register row exists (Observer flips this to "issued");
            // "cleared"/"voided" are synced from the linked check_register row's status.
            $table->enum('status', ['draft', 'issued', 'cleared', 'voided'])->default('draft');
            $table->string('particulars');
            $table->foreignId('cost_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('null for PCF replenishment - cost accounts live on the PCV items');
            $table->string('payee_name');
            $table->string('address')->nullable();
            $table->string('si_no')->nullable();
            $table->string('tin')->nullable();
            $table->decimal('amount_w_vat', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('net_purchases', 14, 2)->default(0);
            $table->decimal('vat_exempt', 14, 2)->default(0);
            $table->decimal('non_vat_purchase', 14, 2)->default(0);
            $table->decimal('ewt_rate', 5, 4)->default(0);
            $table->decimal('ewt_amount', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_vouchers');
    }
};
