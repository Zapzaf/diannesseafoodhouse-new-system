<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log: one row per promotional discount ever applied to an order,
     * independent of the order's own promo_* columns (which only hold the
     * *current* promo, and get overwritten if the order is edited before
     * payment). This table is append-only and is the source of truth for
     * "all discount transactions must be logged for auditing".
     */
    public function up(): void
    {
        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source'); // coupon | automatic | manual
            $table->string('code_used')->nullable();
            $table->string('label')->nullable();
            $table->string('discount_type')->nullable(); // percentage | fixed
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('discount_amount', 14, 2);
            $table->foreignId('applied_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('applied'); // applied | released
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['discount_campaign_id', 'status']);
            $table->index(['menu_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
    }
};
