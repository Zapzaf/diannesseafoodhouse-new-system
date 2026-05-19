<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category'); // denormalized category name for display
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'name']);
            $table->index(['branch_id', 'category']);
        });

        Schema::create('menu_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_required', 14, 2)->default(0.01);
            $table->timestamps();

            $table->unique(['menu_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_inventory');
        Schema::dropIfExists('menus');
    }
};