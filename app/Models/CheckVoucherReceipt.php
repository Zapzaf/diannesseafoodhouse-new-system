<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckVoucherReceipt extends Model
{
    protected $fillable = [
        'check_voucher_id',
        'si_no',
        'amount_w_vat',
        'vat',
        'net_purchases',
        'vat_exempt',
        'non_vat_purchase',
    ];

    protected function casts(): array
    {
        return [
            'amount_w_vat' => 'decimal:2',
            'vat' => 'decimal:2',
            'net_purchases' => 'decimal:2',
            'vat_exempt' => 'decimal:2',
            'non_vat_purchase' => 'decimal:2',
        ];
    }

    public function getTotalAttribute(): float
    {
        return round((float) $this->amount_w_vat + (float) $this->vat_exempt + (float) $this->non_vat_purchase, 2);
    }

    public function checkVoucher(): BelongsTo
    {
        return $this->belongsTo(CheckVoucher::class);
    }
}
