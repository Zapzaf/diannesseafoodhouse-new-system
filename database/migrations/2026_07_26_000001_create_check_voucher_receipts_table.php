<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('check_voucher_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_voucher_id')->constrained()->cascadeOnDelete();
            $table->string('si_no')->nullable();
            $table->decimal('amount_w_vat', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('net_purchases', 14, 2)->default(0);
            $table->decimal('vat_exempt', 14, 2)->default(0);
            $table->decimal('non_vat_purchase', 14, 2)->default(0);
            $table->timestamps();
        });

        // Backfill: give every existing standalone CV (COD / other disbursement) a single
        // receipt row mirroring its current si_no/amount fields, so history isn't lost.
        $vouchers = DB::table('check_vouchers')
            ->whereIn('type', ['cod_purchase', 'other_disbursement'])
            ->where(function ($query) {
                $query->whereNotNull('si_no')
                    ->orWhere('amount_w_vat', '>', 0)
                    ->orWhere('vat_exempt', '>', 0)
                    ->orWhere('non_vat_purchase', '>', 0);
            })
            ->get();

        foreach ($vouchers as $voucher) {
            DB::table('check_voucher_receipts')->insert([
                'check_voucher_id' => $voucher->id,
                'si_no' => $voucher->si_no,
                'amount_w_vat' => $voucher->amount_w_vat,
                'vat' => $voucher->vat,
                'net_purchases' => $voucher->net_purchases,
                'vat_exempt' => $voucher->vat_exempt,
                'non_vat_purchase' => $voucher->non_vat_purchase,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_voucher_receipts');
    }
};
