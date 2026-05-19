<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuOrderPayment extends Model
{
    protected $fillable = [
        'branch_id', 'menu_order_id', 'amount', 'amount_tendered', 'change_amount', 'subtotal',
        'additional_charge_label', 'additional_charge_amount',
        'total_vat_exempt', 'total_discount', 'final_total',
        'discount_type', 'discount_amount', 'vat_amount', 'is_vat_exempt',
        'method', 'reference_number', 'or_number', 'payment_date',
        'notes', 'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'additional_charge_amount' => 'decimal:2',
        'total_vat_exempt' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'final_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'is_vat_exempt' => 'boolean',
        'payment_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MenuOrder::class, 'menu_order_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}