<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('opening_float', 14, 2)->default(0);
            $table->timestamp('opened_at');
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('open'); // open | closed
            $table->decimal('closing_cash_counted', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('cash_variance', 14, 2)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['pos_terminal_id', 'status']);
            $table->index(['cashier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};
