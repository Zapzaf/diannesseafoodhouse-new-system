<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->foreignId('source_item_id')
                ->nullable()
                ->after('item_id')
                ->constrained('items')
                ->nullOnDelete();

            $table->index(['source_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_item_id');
        });
    }
};

