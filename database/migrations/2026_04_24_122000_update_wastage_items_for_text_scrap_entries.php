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
        Schema::table('wastage_items', function (Blueprint $table) {
            $table->string('scrap_name', 255)->nullable()->after('item_id');

            $table->dropForeign(['item_id']);
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wastage_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();

            $table->dropColumn('scrap_name');
        });
    }
};