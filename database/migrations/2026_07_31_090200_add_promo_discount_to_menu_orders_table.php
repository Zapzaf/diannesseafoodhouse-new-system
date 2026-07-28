<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_orders', function (Blueprint $table) {
            $table->foreignId('promo_campaign_id')->nullable()->after('discount_amount')->constrained('discount_campaigns')->nullOnDelete();
            $table->string('promo_discount_source')->nullable()->after('promo_campaign_id'); // coupon | automatic | manual
            $table->string('promo_discount_code')->nullable()->after('promo_discount_source');
            $table->string('promo_discount_label')->nullable()->after('promo_discount_code');
            $table->decimal('promo_discount_amount', 14, 2)->default(0)->after('promo_discount_label');
        });
    }

    public function down(): void
    {
        Schema::table('menu_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_campaign_id');
            $table->dropColumn(['promo_discount_source', 'promo_discount_code', 'promo_discount_label', 'promo_discount_amount']);
        });
    }
};
