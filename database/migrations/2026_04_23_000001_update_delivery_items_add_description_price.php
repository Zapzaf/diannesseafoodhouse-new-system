<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('delivery_id');
            $table->decimal('price', 14, 2)->nullable()->after('unit');
            $table->unsignedBigInteger('item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['description', 'price']);
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
        });
    }
};
