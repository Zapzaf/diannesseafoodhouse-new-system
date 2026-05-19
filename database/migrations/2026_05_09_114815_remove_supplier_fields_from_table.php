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
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'supplier_contact']);
        });
    }

    public function down(): void
    {
        Schema::table('your_table_name', function (Blueprint $table) {
            $table->string('supplier_name')->nullable();
            $table->string('supplier_contact')->nullable();
        });
    }
};
