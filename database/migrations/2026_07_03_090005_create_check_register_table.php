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
        Schema::create('check_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_voucher_id')->unique()->constrained('check_vouchers')->cascadeOnDelete();
            $table->date('check_date');
            $table->string('check_no')->unique();
            $table->string('payee');
            $table->string('particulars');
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['issued', 'cleared', 'voided'])->default('issued');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_register');
    }
};
