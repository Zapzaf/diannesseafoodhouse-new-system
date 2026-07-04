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
        Schema::dropIfExists('vatable_purchases');
        Schema::dropIfExists('non_vatable_purchases');
        Schema::dropIfExists('cash_disbursements');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('vatable_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('date')->nullable();
            $table->string('vendor_name');
            $table->string('address')->nullable();
            $table->string('si_number')->nullable();
            $table->string('tin')->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('net_purchases', 12, 2)->default(0);
            $table->string('month_year');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::create('non_vatable_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('date')->nullable();
            $table->string('vendor_name');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->string('month_year');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::create('cash_disbursements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('date')->nullable();
            $table->string('check_number')->nullable();
            $table->string('payee');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference')->nullable();
            $table->string('month_year');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }
};
