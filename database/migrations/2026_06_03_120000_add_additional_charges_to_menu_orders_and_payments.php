<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_orders', function (Blueprint $table) {
            $table->text('additional_charges')->nullable()->after('additional_charge_amount');
        });

        Schema::table('menu_order_payments', function (Blueprint $table) {
            $table->text('additional_charges')->nullable()->after('additional_charge_amount');
        });

        DB::table('menu_orders')
            ->select(['id', 'additional_charge_label', 'additional_charge_amount'])
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $amount = round((float) ($order->additional_charge_amount ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    DB::table('menu_orders')
                        ->where('id', $order->id)
                        ->update([
                            'additional_charges' => json_encode([[
                                'label' => trim((string) ($order->additional_charge_label ?: 'Additional Charge')),
                                'type' => 'fixed',
                                'value' => $amount,
                                'amount' => $amount,
                                'base_subtotal' => null,
                            ]]),
                        ]);
                }
            });

        DB::table('menu_order_payments')
            ->select(['id', 'additional_charge_label', 'additional_charge_amount'])
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    $amount = round((float) ($payment->additional_charge_amount ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    DB::table('menu_order_payments')
                        ->where('id', $payment->id)
                        ->update([
                            'additional_charges' => json_encode([[
                                'label' => trim((string) ($payment->additional_charge_label ?: 'Additional Charge')),
                                'type' => 'fixed',
                                'value' => $amount,
                                'amount' => $amount,
                                'base_subtotal' => null,
                            ]]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('menu_order_payments', function (Blueprint $table) {
            $table->dropColumn('additional_charges');
        });

        Schema::table('menu_orders', function (Blueprint $table) {
            $table->dropColumn('additional_charges');
        });
    }
};
