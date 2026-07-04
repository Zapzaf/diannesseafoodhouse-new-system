<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Materials & Supplies', 'type' => 'debit_expense'],
            ['name' => 'Janitorial & Cleaning Supplies', 'type' => 'debit_expense'],
            ['name' => 'Gasoline & Supplies', 'type' => 'debit_expense'],
            ['name' => 'Transportation', 'type' => 'debit_expense'],
            ['name' => 'Electricity', 'type' => 'debit_expense'],
            ['name' => 'Accounts Payable - Trade', 'type' => 'credit_liability'],
            ['name' => 'Advances - Employees', 'type' => 'credit_liability'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(['name' => $account['name']], $account);
        }
    }
}
