<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds the Purchase/Service inventory category taxonomy as named accounts,
     * reusing the existing Chart of Accounts picker instead of a separate schema.
     */
    private const ACCOUNTS = [
        ['name' => 'Materials & Supplies', 'type' => 'debit_expense'],
        ['name' => 'Beverage', 'type' => 'debit_expense'],
        ['name' => 'Ice Cream', 'type' => 'debit_expense'],
        ['name' => 'Houseware & Cleaning', 'type' => 'debit_expense'],
        ['name' => 'Others', 'type' => 'debit_expense'],
        ['name' => 'Asset', 'type' => 'debit_asset'],
        ['name' => 'Electricity', 'type' => 'debit_expense'],
        ['name' => 'Water', 'type' => 'debit_expense'],
        ['name' => 'Internet', 'type' => 'debit_expense'],
        ['name' => 'Telephone', 'type' => 'debit_expense'],
        ['name' => 'Repairs', 'type' => 'debit_expense'],
        ['name' => 'Professional Fees', 'type' => 'debit_expense'],
        ['name' => 'Advances - KDs', 'type' => 'debit_asset'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ACCOUNTS as $account) {
            $exists = DB::table('chart_of_accounts')->where('name', $account['name'])->exists();

            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('chart_of_accounts')->whereIn('name', array_column(self::ACCOUNTS, 'name'))->delete();
    }
};
