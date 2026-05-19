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
        Schema::table('production_inputs', function (Blueprint $table) {
            $table->foreignId('delivery_item_id')->nullable()->after('item_id')->constrained('delivery_items')->nullOnDelete();
            $table->unique('delivery_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_inputs', function (Blueprint $table) {
            $table->dropUnique(['delivery_item_id']);
            $table->dropConstrainedForeignId('delivery_item_id');
        });
    }
};