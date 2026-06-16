<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'items',
        'delivery_items',
        'production_inputs',
        'production_outputs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit')) {
                continue;
            }

            DB::table($table)
                ->whereIn(DB::raw('LOWER(TRIM(unit))'), ['pc', 'piece', 'pieces'])
                ->update(['unit' => 'pcs']);
        }
    }

    public function down(): void
    {
        // Intentionally left unchanged because existing valid "pcs" values
        // cannot be distinguished from values normalized by this migration.
    }
};
