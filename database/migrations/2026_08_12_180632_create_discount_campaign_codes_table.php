<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets one campaign carry several coupon codes (same discount rules,
     * different strings — e.g. distributed to different channels/partners).
     * Replaces discount_campaigns.code, which only allowed one code per
     * campaign.
     */
    public function up(): void
    {
        Schema::create('discount_campaign_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->timestamps();

            // Global uniqueness, same as the old discount_campaigns.code column —
            // a code must resolve to exactly one campaign.
            $table->unique('code');
        });

        // Carry forward every campaign's existing single code as its first row.
        DB::table('discount_campaigns')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->select('id', 'code')
            ->orderBy('id')
            ->each(function ($campaign): void {
                DB::table('discount_campaign_codes')->insert([
                    'discount_campaign_id' => $campaign->id,
                    'code' => $campaign->code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->string('code')->nullable()->after('description');
        });

        // Restore each campaign's first code (best-effort — a campaign with
        // more than one code loses the extras on rollback).
        DB::table('discount_campaign_codes')
            ->orderBy('id')
            ->select('discount_campaign_id', 'code')
            ->each(function ($row): void {
                DB::table('discount_campaigns')
                    ->where('id', $row->discount_campaign_id)
                    ->whereNull('code')
                    ->update(['code' => $row->code]);
            });

        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::dropIfExists('discount_campaign_codes');
    }
};
