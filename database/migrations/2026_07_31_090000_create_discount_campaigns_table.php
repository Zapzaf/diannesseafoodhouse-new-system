<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->id();
            // Nullable branch_id = valid company-wide, across all branches.
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Presence of a code makes this a "coupon" a cashier must type in;
            // a null code makes it an "automatic" campaign applied silently
            // whenever an order meets its conditions.
            $table->string('code')->nullable();
            $table->string('type'); // percentage | fixed
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount_amount', 14, 2)->nullable();
            $table->decimal('min_purchase_amount', 14, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('code');
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_campaigns');
    }
};
