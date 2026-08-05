<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * total_purchases was previously computed as
     * net_purchases + vat_exempt + non_vat_purchase, which silently dropped
     * the VAT amount from the total. Redefine it as
     * net_purchases + vat + vat_exempt + non_vat_purchase (equivalent to
     * amount_w_vat + vat_exempt + non_vat_purchase) to match payable_total
     * calculations used elsewhere in the app.
     */
    public function up(): void
    {
        if (Schema::hasColumn('purchase_voucher_items', 'total_purchases')) {
            DB::statement('ALTER TABLE purchase_voucher_items MODIFY total_purchases DECIMAL(14,2) AS (net_purchases + vat + vat_exempt + non_vat_purchase) STORED');
        }

        if (Schema::hasColumn('services', 'total_purchases')) {
            DB::statement('ALTER TABLE services MODIFY total_purchases DECIMAL(14,2) AS (net_purchases + vat + vat_exempt + non_vat_purchase) STORED');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_voucher_items', 'total_purchases')) {
            DB::statement('ALTER TABLE purchase_voucher_items MODIFY total_purchases DECIMAL(14,2) AS (net_purchases + vat_exempt + non_vat_purchase) STORED');
        }

        if (Schema::hasColumn('services', 'total_purchases')) {
            DB::statement('ALTER TABLE services MODIFY total_purchases DECIMAL(14,2) AS (net_purchases + vat_exempt + non_vat_purchase) STORED');
        }
    }
};
