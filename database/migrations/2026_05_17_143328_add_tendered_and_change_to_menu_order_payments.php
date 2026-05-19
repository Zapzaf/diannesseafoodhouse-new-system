<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_order_payments', function (Blueprint $table) {
            $table->decimal('amount_tendered', 12, 2)->default(0)->after('amount');
            $table->decimal('change_amount', 12, 2)->default(0)->after('amount_tendered');
        });
    }

    public function down(): void
    {
        Schema::table('menu_order_payments', function (Blueprint $table) {
            $table->dropColumn(['amount_tendered', 'change_amount']);
        });
    }
};
