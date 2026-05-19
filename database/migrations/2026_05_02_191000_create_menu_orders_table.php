<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('additional_charge_label')->nullable();
            $table->decimal('additional_charge_amount', 14, 2)->default(0);
            $table->integer('regular_pax')->default(1);
            $table->integer('pwd_pax')->default(0);
            $table->integer('senior_pax')->default(0);
            $table->integer('total_pax')->default(1);
            $table->string('discount_type')->default('none');
            $table->string('discount_id_number')->nullable();
            $table->string('discount_name')->nullable();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_vat_exempt', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(12.00);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('menu_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('profit', 14, 2)->default(0);
            $table->boolean('inventory_deducted')->default(false);
            $table->timestamps();
        });

        Schema::create('menu_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('additional_charge_label')->nullable();
            $table->decimal('additional_charge_amount', 14, 2)->default(0);
            $table->decimal('total_vat_exempt', 14, 2)->default(0);
            $table->decimal('total_discount', 14, 2)->default(0);
            $table->decimal('final_total', 14, 2)->default(0);
            $table->string('discount_type')->default('none');
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->boolean('is_vat_exempt')->default(false);
            $table->string('method')->default('cash');
            $table->string('reference_number')->nullable();
            $table->string('or_number')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_order_payments');
        Schema::dropIfExists('menu_order_items');
        Schema::dropIfExists('menu_orders');
    }
};