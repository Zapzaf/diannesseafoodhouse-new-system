<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCampaignCode extends Model
{
    protected $fillable = [
        'discount_campaign_id',
        'code',
        'usage_limit',
        'usage_count',
    ];

    protected $casts = [
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'discount_campaign_id');
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }
}
