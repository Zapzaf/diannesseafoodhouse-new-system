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
        Schema::table('check_vouchers', function (Blueprint $table): void {
            $table->foreignId('bank_account_id')->nullable()->after('cost_account_id')->constrained('bank_accounts')->nullOnDelete();
            $table->enum('payment_method', ['cash', 'check', 'bank_transfer', 'online'])->default('check')->after('bank_account_id');
            $table->string('reference_no')->nullable()->unique()->after('cv_no');
            $table->foreignId('service_id')->nullable()->after('purchase_voucher_id')
                ->constrained('services')->nullOnDelete()
                ->comment('set when this CV pays an existing Service');
            $table->foreignId('advance_account_id')->nullable()->after('service_id')
                ->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('set when type = advance; credit_liability (advance to a person) or debit_asset (Advances - KDs)');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->enum('type', ['pcf_replenishment', 'apv_payment', 'cod_purchase', 'service_payment', 'service_cod', 'advance', 'other_disbursement'])
                ->default('other_disbursement')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('advance_account_id');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['payment_method', 'reference_no']);

            $table->enum('type', ['pcf_replenishment', 'apv_payment', 'cod_purchase', 'other_disbursement'])
                ->default('other_disbursement')->change();
        });
    }
};
