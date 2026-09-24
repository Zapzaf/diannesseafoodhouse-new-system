<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckVoucherPurchaseVoucher extends Model
{
    protected $fillable = [
        'check_voucher_id',
        'purchase_voucher_id',
        'amount_w_vat',
    ];

    protected function casts(): array
    {
        return [
            'amount_w_vat' => 'decimal:2',
        ];
    }

    public function checkVoucher(): BelongsTo
    {
        return $this->belongsTo(CheckVoucher::class);
    }

    public function purchaseVoucher(): BelongsTo
    {
        return $this->belongsTo(PurchaseVoucher::class);
    }
}
