<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expands Delivery to capture the same BIR/tax detail Check Vouchers and
     * Purchase Vouchers already do, so stock deliveries can be fully encoded
     * (with VAT/EWT split) at the point of receipt instead of needing a
     * separate APV entry — one field set per delivery (one invoice/receipt),
     * mirroring CheckVoucher's amount_w_vat/vat/net_purchases/vat_exempt/
     * non_vat_purchase/ewt_rate/ewt_amount shape exactly.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('tin')->nullable()->after('supplier_id');
            $table->string('address')->nullable()->after('tin');
            $table->string('si_no')->nullable()->after('address');
            $table->decimal('amount_w_vat', 14, 2)->default(0)->after('si_no');
            $table->decimal('vat', 14, 2)->default(0)->after('amount_w_vat');
            $table->decimal('net_purchases', 14, 2)->default(0)->after('vat');
            $table->decimal('vat_exempt', 14, 2)->default(0)->after('net_purchases');
            $table->decimal('non_vat_purchase', 14, 2)->default(0)->after('vat_exempt');
            $table->decimal('ewt_rate', 5, 4)->default(0)->after('non_vat_purchase');
            $table->decimal('ewt_amount', 14, 2)->default(0)->after('ewt_rate');
            $table->text('approval_remarks')->nullable()->after('approved_at');
            $table->text('rejection_remarks')->nullable()->after('approval_remarks');
            // Same portable pattern already used to widen production_orders.status
            // (see add_cancelled_status_to_production_orders_table) — cross-driver,
            // including SQLite (used by the test suite). Existing 'pending'/
            // 'received' rows are unaffected; this only adds 'rejected'.
            $table->enum('status', ['pending', 'received', 'rejected'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'tin', 'address', 'si_no',
                'amount_w_vat', 'vat', 'net_purchases', 'vat_exempt', 'non_vat_purchase',
                'ewt_rate', 'ewt_amount',
                'approval_remarks', 'rejection_remarks',
            ]);
            $table->enum('status', ['pending', 'received'])->default('pending')->change();
        });
    }
};
