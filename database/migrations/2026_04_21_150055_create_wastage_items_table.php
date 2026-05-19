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
        Schema::create('wastage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wastage_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_lost', 14, 2);
            $table->string('reason')->nullable();
            $table->foreignId('convert_to_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->decimal('converted_quantity', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastage_items');
    }
};
