<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCampaign extends Model
{
    protected $fillable = [
        'branch_id', 'name', 'description', 'code', 'type', 'value',
        'max_discount_amount', 'min_purchase_amount',
        'starts_at', 'ends_at', 'usage_limit', 'usage_count',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function isCoupon(): bool
    {
        return !empty($this->code);
    }

    /**
     * Whether this campaign can currently be used for a purchase of the
     * given amount at the given branch. Does not check/consume usage —
     * callers that intend to redeem it must lock the row and re-check
     * usage_limit inside a transaction to avoid a race.
     *
     * @return array{0: bool, 1: ?string} [eligible, reason-if-not]
     */
    public function eligibilityFor(?int $branchId, float $purchaseAmount): array
    {
        if (!$this->is_active) {
            return [false, 'This discount is not currently active.'];
        }

        if ($this->branch_id !== null && $branchId !== null && (int) $this->branch_id !== (int) $branchId) {
            return [false, 'This discount is not valid for the selected branch.'];
        }

        $today = now()->toDateString();

        if ($this->starts_at && $today < $this->starts_at->toDateString()) {
            return [false, 'This discount is not valid yet.'];
        }

        if ($this->ends_at && $today > $this->ends_at->toDateString()) {
            return [false, 'This discount has expired.'];
        }

        if ((float) $this->min_purchase_amount > 0 && $purchaseAmount < (float) $this->min_purchase_amount) {
            return [false, 'This discount requires a minimum purchase of ₱' . number_format((float) $this->min_purchase_amount, 2) . '.'];
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return [false, 'This discount has reached its usage limit.'];
        }

        return [true, null];
    }

    public function computeDiscountAmount(float $purchaseAmount): float
    {
        $amount = $this->type === 'percentage'
            ? $purchaseAmount * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount_amount !== null) {
            $amount = min($amount, (float) $this->max_discount_amount);
        }

        return round(min(max(0, $amount), $purchaseAmount), 2);
    }
}
