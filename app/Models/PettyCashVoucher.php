<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashVoucher extends Model
{
    protected $fillable = [
        'date',
        'pcv_no',
        'check_voucher_id',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PettyCashVoucherItem::class);
    }

    public function checkVoucher(): BelongsTo
    {
        return $this->belongsTo(CheckVoucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn (PettyCashVoucherItem $item) => $item->total_purchases);
    }

    public function isReplenished(): bool
    {
        return $this->check_voucher_id !== null;
    }
}
