<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CV numbers were globally unique, so the same number (e.g. 2595) couldn't be
     * reused across different Disbursement Types even though the client treats
     * each type as its own numbering series. Scope uniqueness to (type, cv_no)
     * instead so the same number is only rejected within the same type.
     */
    public function up(): void
    {
        Schema::table('check_vouchers', function (Blueprint $table) {
            $table->dropUnique(['cv_no']);
            $table->unique(['type', 'cv_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_vouchers', function (Blueprint $table) {
            $table->dropUnique(['type', 'cv_no']);
            $table->unique('cv_no');
        });
    }
};
