<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashVoucherItem extends Model
{
    protected $fillable = [
        'petty_cash_voucher_id',
        'quantity',
        'unit',
        'particulars',
        'cost_account_id',
        'amount_w_vat',
        'vat',
        'net_purchases',
        'vat_exempt',
        'non_vat_purchase',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'amount_w_vat' => 'decimal:2',
            'vat' => 'decimal:2',
            'net_purchases' => 'decimal:2',
            'vat_exempt' => 'decimal:2',
            'non_vat_purchase' => 'decimal:2',
        ];
    }

    public function pettyCashVoucher(): BelongsTo
    {
        return $this->belongsTo(PettyCashVoucher::class);
    }

    public function costAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cost_account_id');
    }

    public function getTotalPurchasesAttribute(): float
    {
        return (float) $this->net_purchases + (float) $this->vat_exempt + (float) $this->non_vat_purchase;
    }
}
