<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRedemption extends Model
{
    protected $fillable = [
        'menu_order_id', 'branch_id', 'discount_campaign_id', 'discount_campaign_code_id',
        'source', 'code_used', 'label', 'discount_type', 'discount_value',
        'discount_amount', 'applied_by', 'status', 'released_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'released_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MenuOrder::class, 'menu_order_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'discount_campaign_id');
    }

    public function campaignCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaignCode::class, 'discount_campaign_code_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
