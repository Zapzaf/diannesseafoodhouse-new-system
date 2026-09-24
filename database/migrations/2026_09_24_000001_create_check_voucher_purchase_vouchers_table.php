<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_voucher_purchase_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_voucher_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_w_vat', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['check_voucher_id', 'purchase_voucher_id'], 'cvpv_cv_pv_index');
        });

        // Existing single-APV payments move to the allocation table so every
        // apv_payment CV reads through one path. cod_purchase CVs keep using
        // the scalar purchase_voucher_id column exclusively.
        DB::table('check_vouchers')
            ->where('type', 'apv_payment')
            ->whereNotNull('purchase_voucher_id')
            ->get(['id', 'purchase_voucher_id', 'amount_w_vat'])
            ->each(fn ($cv) => DB::table('check_voucher_purchase_vouchers')->insert([
                'check_voucher_id' => $cv->id,
                'purchase_voucher_id' => $cv->purchase_voucher_id,
                'amount_w_vat' => $cv->amount_w_vat,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('check_voucher_purchase_vouchers');
    }
};
