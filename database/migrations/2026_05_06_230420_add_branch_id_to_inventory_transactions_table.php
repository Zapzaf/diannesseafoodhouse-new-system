<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('item_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index(['branch_id', 'created_at']);
        });

        DB::table('items')
            ->select(['id', 'branch_id'])
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    DB::table('inventory_transactions')
                        ->where('item_id', $item->id)
                        ->whereNull('branch_id')
                        ->update(['branch_id' => $item->branch_id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};

