<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->enum('status', ['in_progress', 'finished', 'cancelled'])->default('in_progress')->change();
            $table->timestamp('cancelled_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
            $table->enum('status', ['in_progress', 'finished'])->default('in_progress')->change();
        });
    }
};
