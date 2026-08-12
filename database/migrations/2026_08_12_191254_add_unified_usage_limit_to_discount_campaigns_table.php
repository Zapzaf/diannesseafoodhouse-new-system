<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an admin choose, per campaign, whether the Usage Limit is one
     * pooled number shared by every coupon code (unified — the simple,
     * least-confusing default) or each code tracks its own limit
     * individually (unified off — what the previous per-code-only setup
     * always did). Mutually exclusive: only one of campaign-level
     * usage_limit or each code's own usage_limit is ever enforced for a
     * given campaign.
     *
     * Also tracks, per redemption, which pool (campaign or code) actually
     * absorbed that usage — so releasing a redemption later (order edited
     * or cancelled) credits back the right counter even if the campaign's
     * unified setting is changed afterwards.
     */
    public function up(): void
    {
        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->boolean('unified_usage_limit')->default(true)->after('usage_count');
        });

        // Existing campaigns were created back when every code always had its
        // own individual limit — keep that behavior for them unchanged.
        DB::table('discount_campaigns')->update(['unified_usage_limit' => false]);

        Schema::table('discount_redemptions', function (Blueprint $table) {
            $table->boolean('campaign_pool_consumed')->default(false)->after('discount_campaign_code_id');
        });

        // Backfill: every redemption recorded so far either had no code
        // (automatic — always pooled) or had a code (coupon — always
        // per-code under the old behavior).
        DB::table('discount_redemptions')
            ->whereNotNull('discount_campaign_id')
            ->whereNull('discount_campaign_code_id')
            ->update(['campaign_pool_consumed' => true]);
    }

    public function down(): void
    {
        Schema::table('discount_redemptions', function (Blueprint $table) {
            $table->dropColumn('campaign_pool_consumed');
        });

        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->dropColumn('unified_usage_limit');
        });
    }
};
