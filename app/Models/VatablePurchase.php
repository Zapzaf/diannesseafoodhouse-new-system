<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatablePurchase extends Model
{
    protected $fillable = [
        'branch_id', 'date', 'vendor_name', 'address', 'si_number',
        'tin', 'gross_amount', 'vat', 'net_purchases', 'month_year'
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'vat' => 'decimal:2',
        'net_purchases' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
