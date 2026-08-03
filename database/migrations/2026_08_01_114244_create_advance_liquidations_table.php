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
        Schema::create('advance_liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_voucher_id')->constrained('check_vouchers')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_liquidations');
    }
};
