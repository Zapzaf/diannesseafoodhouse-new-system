<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery → APV Auto-Transfer
    |--------------------------------------------------------------------------
    |
    | When a supplier delivery is approved, an Accounts Payable Voucher (APV)
    | is generated from it automatically. These are the Chart of Accounts
    | names used on that APV. If an account with the name doesn't exist yet
    | it is created (cost account as debit_expense, credit account as
    | credit_liability).
    |
    */

    'delivery_apv' => [
        'cost_account' => env('DELIVERY_APV_COST_ACCOUNT', 'Materials & Supplies'),
        'credit_account' => env('DELIVERY_APV_CREDIT_ACCOUNT', 'Accounts Payable - Trade'),
    ],

];
