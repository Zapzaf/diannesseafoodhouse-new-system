<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('beginning_quantity', 14, 2)->nullable()->after('quantity');
            $table->decimal('transaction_price', 14, 4)->nullable()->after('beginning_quantity');
            $table->timestamp('transaction_date')->nullable()->after('transaction_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn(['beginning_quantity', 'transaction_price', 'transaction_date']);
        });
    }
};
