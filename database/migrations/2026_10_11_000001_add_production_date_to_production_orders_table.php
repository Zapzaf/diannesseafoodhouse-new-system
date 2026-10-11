<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->date('production_date')->nullable()->after('status');
        });

        // Existing orders started on the day they were created.
        DB::table('production_orders')->update(['production_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropColumn('production_date');
        });
    }
};
