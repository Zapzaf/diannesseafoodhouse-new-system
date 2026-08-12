<?php

namespace App\Services;

use App\Models\DiscountCampaign;
use App\Models\DiscountCampaignCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves promotional discounts (coupons and automatic campaigns) for a
 * menu order. This is entirely separate from — and never touches — the
 * PWD/Senior Citizen discount computation, which stays governed by RA 9257 /
 * RA 10754 rules elsewhere in MenuOrderController.
 */
class DiscountCampaignService
{
    /**
     * @return array{ok: bool, campaign: ?DiscountCampaign, campaign_code: ?DiscountCampaignCode, amount: float, message: ?string}
     */
    public function validateCoupon(string $code, ?int $branchId, float $purchaseAmount): array
    {
        $code = trim($code);

        if ($code === '') {
            return ['ok' => false, 'campaign' => null, 'campaign_code' => null, 'amount' => 0.0, 'message' => 'Enter a coupon code.'];
        }

        // Any code belonging to the campaign's coupon codes resolves to that
        // campaign — several codes can share the same discount rules, but
        // each code tracks (and caps) its own usage independently.
        $campaignCode = DiscountCampaignCode::where('code', $code)->with('campaign')->first();
        $campaign = $campaignCode?->campaign;

        if (!$campaignCode || !$campaign) {
            return ['ok' => false, 'campaign' => null, 'campaign_code' => null, 'amount' => 0.0, 'message' => 'This coupon code was not found.'];
        }

        [$eligible, $reason] = $campaign->eligibilityFor($branchId, $purchaseAmount);

        if (!$eligible) {
            return ['ok' => false, 'campaign' => $campaign, 'campaign_code' => $campaignCode, 'amount' => 0.0, 'message' => $reason];
        }

        if ($campaignCode->isExhausted()) {
            return ['ok' => false, 'campaign' => $campaign, 'campaign_code' => $campaignCode, 'amount' => 0.0, 'message' => 'This coupon code has reached its usage limit.'];
        }

        return [
            'ok' => true,
            'campaign' => $campaign,
            'campaign_code' => $campaignCode,
            'amount' => $campaign->computeDiscountAmount($purchaseAmount),
            'message' => null,
        ];
    }

    /**
     * Finds the automatic (no-code) campaign that yields the largest
     * discount for this branch/purchase amount, among all that are
     * currently eligible. Returns null if none qualify.
     */
    public function findBestAutomaticCampaign(?int $branchId, float $purchaseAmount): ?DiscountCampaign
    {
        $candidates = DiscountCampaign::query()
            ->whereDoesntHave('codes')
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->get();

        $best = null;
        $bestAmount = 0.0;

        foreach ($candidates as $campaign) {
            [$eligible] = $campaign->eligibilityFor($branchId, $purchaseAmount);

            if (!$eligible) {
                continue;
            }

            $amount = $campaign->computeDiscountAmount($purchaseAmount);

            if ($amount > $bestAmount) {
                $best = $campaign;
                $bestAmount = $amount;
            }
        }

        return $best;
    }
}
