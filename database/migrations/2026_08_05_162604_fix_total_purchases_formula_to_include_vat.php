<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $this->redefine('net_purchases + vat + vat_exempt + non_vat_purchase');
    }

    public function down(): void
    {
        $this->redefine('net_purchases + vat_exempt + non_vat_purchase');
    }

    private function redefine(string $formula): void
    {
        // SQLite (used by the test suite) can't ALTER MODIFY a generated
        // column's expression — the column has to be dropped and re-added.
        // MySQL supports the in-place MODIFY this migration originally used.
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (['purchase_voucher_items', 'services'] as $table) {
            if (! Schema::hasColumn($table, 'total_purchases')) {
                continue;
            }

            if ($isSqlite) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('total_purchases'));
                Schema::table($table, fn (Blueprint $t) => $t->decimal('total_purchases', 14, 2)->storedAs($formula));

                continue;
            }

            DB::statement("ALTER TABLE {$table} MODIFY total_purchases DECIMAL(14,2) AS ({$formula}) STORED");
        }
    }
};
