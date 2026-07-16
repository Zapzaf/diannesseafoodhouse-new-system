<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_vouchers', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('branch_id')->constrained('suppliers')->nullOnDelete();
        });

        Schema::table('check_vouchers', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('branch_id')->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('check_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
