<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coupon codes within a campaign share the same discount type/value, but
     * each is redeemable independently — usage is now tracked and capped per
     * code (default 1 use) instead of pooled on the campaign. The campaign's
     * own usage_limit/usage_count remain in effect only for "automatic"
     * (no-code) campaigns.
     */
    public function up(): void
    {
        Schema::table('discount_campaign_codes', function (Blueprint $table) {
            $table->unsignedInteger('usage_limit')->nullable()->default(1)->after('code');
            $table->unsignedInteger('usage_count')->default(0)->after('usage_limit');
        });

        Schema::table('discount_redemptions', function (Blueprint $table) {
            // Which specific code was consumed, so releasing a redemption
            // (order edited/cancelled) can credit the same code back —
            // independent of code_used, which stays a plain audit string.
            $table->foreignId('discount_campaign_code_id')->nullable()->after('discount_campaign_id')
                ->constrained('discount_campaign_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discount_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_campaign_code_id');
        });

        Schema::table('discount_campaign_codes', function (Blueprint $table) {
            $table->dropColumn(['usage_limit', 'usage_count']);
        });
    }
};
