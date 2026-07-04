<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wastage_items')) {
            return;
        }

        Schema::table('wastage_items', function (Blueprint $table) {
            if (! Schema::hasColumn('wastage_items', 'scrap_name')) {
                $table->string('scrap_name', 255)->nullable()->after('item_id');
            }

            if (! Schema::hasColumn('wastage_items', 'quantity_lost_unit')) {
                $table->string('quantity_lost_unit', 32)->nullable()->after('quantity_lost');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wastage_items')) {
            return;
        }

        Schema::table('wastage_items', function (Blueprint $table) {
            if (Schema::hasColumn('wastage_items', 'quantity_lost_unit')) {
                $table->dropColumn('quantity_lost_unit');
            }

            if (Schema::hasColumn('wastage_items', 'scrap_name')) {
                $table->dropColumn('scrap_name');
            }
        });
    }
};
