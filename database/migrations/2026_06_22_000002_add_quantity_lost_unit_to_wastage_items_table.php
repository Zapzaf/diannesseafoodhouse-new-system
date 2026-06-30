<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wastage_items', function (Blueprint $table) {
            $table->string('quantity_lost_unit', 32)->nullable()->after('quantity_lost');
        });
    }

    public function down(): void
    {
        Schema::table('wastage_items', function (Blueprint $table) {
            $table->dropColumn('quantity_lost_unit');
        });
    }
};
