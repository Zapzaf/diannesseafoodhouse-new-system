<?php

namespace App\Models;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenuOrder extends Model
{
    protected $fillable = [
        'branch_id', 'customer_name',
        'subtotal', 'additional_charge_label', 'additional_charge_amount',
        'regular_pax', 'pwd_pax', 'senior_pax', 'total_pax',
        'discount_type', 'discount_id_number', 'discount_name', 'discount_amount',
        'total_vat_exempt', 'vat_rate', 'vat_amount',
        'total_amount', 'amount_paid', 'balance',
        'payment_status', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'additional_charge_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_vat_exempt' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuOrderItem::class);
    }

    public function table(): HasOne
    {
        return $this->hasOne(DiningTable::class, 'current_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MenuOrderPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerDisplayName(): string
    {
        return $this->customer_name ?: 'Walk-in Customer';
    }
}