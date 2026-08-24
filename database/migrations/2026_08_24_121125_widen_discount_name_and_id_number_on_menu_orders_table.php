<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * discount_name/discount_id_number store a JSON blob of every PWD/Senior
     * name and ID number on the order (see MenuOrder — discount_type can be
     * 'pwd', 'senior', or 'mixed' with several people on one order). Both
     * columns were plain VARCHAR(255), which is nowhere near enough once an
     * order has more than a couple of PWD/Senior entries — a 14-person order
     * threw "Data too long for column 'discount_name'" on save. TEXT removes
     * that practical limit (up to 64KB, versus 255 bytes).
     */
    public function up(): void
    {
        Schema::table('menu_orders', function (Blueprint $table) {
            $table->text('discount_name')->nullable()->change();
            $table->text('discount_id_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('menu_orders', function (Blueprint $table) {
            $table->string('discount_name')->nullable()->change();
            $table->string('discount_id_number')->nullable()->change();
        });
    }
};
