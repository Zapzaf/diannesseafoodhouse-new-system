<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->string('log_id', 32)->nullable()->after('id');
        });

        DB::table('inventory_transactions')
            ->select(['id', 'transaction_date', 'created_at'])
            ->whereNull('log_id')
            ->orderBy('id')
            ->chunkById(500, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $date = Carbon::parse($transaction->transaction_date ?? $transaction->created_at ?? now());

                    do {
                        $logId = 'TRANS-'.$date->format('Ymd').'-'.Str::upper(Str::random(6));
                    } while (DB::table('inventory_transactions')->where('log_id', $logId)->exists());

                    DB::table('inventory_transactions')
                        ->where('id', $transaction->id)
                        ->update(['log_id' => $logId]);
                }
            });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->unique('log_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique(['log_id']);
            $table->dropColumn('log_id');
        });
    }
};
