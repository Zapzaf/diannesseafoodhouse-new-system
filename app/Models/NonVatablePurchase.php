<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonVatablePurchase extends Model
{
    protected $fillable = [
        'branch_id', 'date', 'vendor_name', 'gross_amount', 'month_year'
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
