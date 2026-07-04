<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseVoucherItem extends Model
{
    protected $fillable = [
        'purchase_voucher_id',
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
            'total_purchases' => 'decimal:2',
        ];
    }

    public function purchaseVoucher(): BelongsTo
    {
        return $this->belongsTo(PurchaseVoucher::class);
    }

    public function costAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cost_account_id');
    }

    public function getPayableAmountAttribute(): float
    {
        return (float) $this->amount_w_vat + (float) $this->vat_exempt + (float) $this->non_vat_purchase;
    }
}
