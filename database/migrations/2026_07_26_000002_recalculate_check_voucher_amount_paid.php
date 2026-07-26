<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data repair: CheckVoucher::applyEwt() used to compute amount_paid from
     * amount_w_vat alone, ignoring vat_exempt/non_vat_purchase. Any CV that was
     * entirely VAT-exempt or non-VAT was saved with amount_paid = 0, and the
     * Check Register (which snapshots amount_paid at check-issuance time) then
     * displayed ₱0.00 for that check even though the CV carried a real amount.
     * This recomputes every check_vouchers row with the corrected formula and
     * re-syncs the linked check_register row so already-issued checks stop
     * showing a stale ₱0.00.
     */
    public function up(): void
    {
        DB::table('check_vouchers')->orderBy('id')->chunkById(200, function ($vouchers): void {
            foreach ($vouchers as $voucher) {
                $amountWVat = (float) $voucher->amount_w_vat;
                $vatExempt = (float) $voucher->vat_exempt;
                $nonVat = (float) $voucher->non_vat_purchase;
                $ewtRate = (float) $voucher->ewt_rate;

                $netPurchases = round($amountWVat / 1.12, 2);
                $ewtAmount = round($netPurchases * $ewtRate, 2);
                $amountPaid = round($amountWVat + $vatExempt + $nonVat - $ewtAmount, 2);

                if ((float) $voucher->ewt_amount === $ewtAmount && (float) $voucher->amount_paid === $amountPaid) {
                    continue;
                }

                DB::table('check_vouchers')->where('id', $voucher->id)->update([
                    'ewt_amount' => $ewtAmount,
                    'amount_paid' => $amountPaid,
                    'updated_at' => now(),
                ]);

                DB::table('check_register')->where('check_voucher_id', $voucher->id)->update([
                    'amount' => $amountPaid,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data-repair migration correcting bad amounts; not meaningfully reversible.
    }
};
