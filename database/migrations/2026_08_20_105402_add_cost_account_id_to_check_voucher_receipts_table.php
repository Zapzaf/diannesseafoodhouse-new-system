<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets a COD Purchase / Other Disbursement CV split its payment across
     * more than one Chart of Accounts entry — each receipt row now carries
     * its own account instead of the whole CV being pinned to a single one.
     * Nullable + backfilled from the parent CV's existing cost_account_id so
     * every receipt keeps behaving exactly as it did before this migration.
     */
    public function up(): void
    {
        Schema::table('check_voucher_receipts', function (Blueprint $table) {
            $table->foreignId('cost_account_id')->nullable()->after('supplier_id')->constrained('chart_of_accounts')->nullOnDelete();
        });

        // Backfill existing rows from their parent CV. MySQL's UPDATE...JOIN
        // syntax isn't portable to SQLite (used by the test suite), which
        // needs UPDATE...FROM instead — same effect, different grammar.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE check_voucher_receipts
                SET cost_account_id = (
                    SELECT check_vouchers.cost_account_id
                    FROM check_vouchers
                    WHERE check_vouchers.id = check_voucher_receipts.check_voucher_id
                )
                WHERE EXISTS (
                    SELECT 1 FROM check_vouchers
                    WHERE check_vouchers.id = check_voucher_receipts.check_voucher_id
                    AND check_vouchers.cost_account_id IS NOT NULL
                )
            ');
        } else {
            DB::statement('
                UPDATE check_voucher_receipts
                INNER JOIN check_vouchers ON check_vouchers.id = check_voucher_receipts.check_voucher_id
                SET check_voucher_receipts.cost_account_id = check_vouchers.cost_account_id
                WHERE check_vouchers.cost_account_id IS NOT NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_voucher_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_account_id');
        });
    }
};
