<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_order_payments', function (Blueprint $table) {
            $table->foreignId('cash_shift_id')->nullable()->after('menu_order_id')->constrained()->nullOnDelete();
            $table->foreignId('pos_terminal_id')->nullable()->after('cash_shift_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_shift_id');
            $table->dropConstrainedForeignId('pos_terminal_id');
        });
    }
};
