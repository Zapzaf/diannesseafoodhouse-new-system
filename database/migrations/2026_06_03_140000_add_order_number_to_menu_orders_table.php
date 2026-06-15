<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_orders', function (Blueprint $table): void {
            $table->string('order_number')->nullable()->after('id');
        });

        $usedOrderNumbers = [];

        DB::table('menu_orders')
            ->select(['id', 'branch_id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$usedOrderNumbers): void {
                foreach ($orders as $order) {
                    $timestamp = Carbon::parse($order->created_at ?? now());
                    $orderNumber = $this->makeUniqueOrderNumber((int) $order->branch_id, $timestamp, $usedOrderNumbers);

                    DB::table('menu_orders')
                        ->where('id', $order->id)
                        ->update(['order_number' => $orderNumber]);
                }
            });

        Schema::table('menu_orders', function (Blueprint $table): void {
            $table->string('order_number')->nullable(false)->change();
            $table->unique('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('menu_orders', function (Blueprint $table): void {
            $table->dropUnique(['order_number']);
            $table->dropColumn('order_number');
        });
    }

    private function makeUniqueOrderNumber(int $branchId, Carbon $timestamp, array &$usedOrderNumbers): string
    {
        do {
            $orderNumber = 'SALES-BR' . $branchId . '-' . $timestamp->format('Ymd-His');
            $timestamp->addSecond();
        } while (isset($usedOrderNumbers[$orderNumber]) || DB::table('menu_orders')->where('order_number', $orderNumber)->exists());

        $usedOrderNumbers[$orderNumber] = true;

        return $orderNumber;
    }
};
