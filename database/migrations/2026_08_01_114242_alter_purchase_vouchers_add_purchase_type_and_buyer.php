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
        Schema::table('purchase_vouchers', function (Blueprint $table): void {
            $table->enum('purchase_type', ['credit', 'cod'])->default('credit')->after('apv_no');
            $table->string('buyer')->nullable()->after('vendor_id');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['purchase_type', 'buyer']);
            $table->foreignId('credit_account_id')->nullable(false)->change();
        });
    }
};
