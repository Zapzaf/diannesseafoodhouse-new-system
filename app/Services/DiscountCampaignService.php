<?php

namespace App\Services;

use App\Models\DiscountCampaign;
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
     * @return array{ok: bool, campaign: ?DiscountCampaign, amount: float, message: ?string}
     */
    public function validateCoupon(string $code, ?int $branchId, float $purchaseAmount): array
    {
        $code = trim($code);

        if ($code === '') {
            return ['ok' => false, 'campaign' => null, 'amount' => 0.0, 'message' => 'Enter a coupon code.'];
        }

        $campaign = DiscountCampaign::where('code', $code)->first();

        if (!$campaign) {
            return ['ok' => false, 'campaign' => null, 'amount' => 0.0, 'message' => 'This coupon code was not found.'];
        }

        [$eligible, $reason] = $campaign->eligibilityFor($branchId, $purchaseAmount);

        if (!$eligible) {
            return ['ok' => false, 'campaign' => $campaign, 'amount' => 0.0, 'message' => $reason];
        }

        return [
            'ok' => true,
            'campaign' => $campaign,
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
            ->whereNull('code')
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
