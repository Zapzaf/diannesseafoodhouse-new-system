<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuOrderItem extends Model
{
    protected $fillable = [
        'menu_order_id', 'menu_id', 'quantity',
        'unit_price', 'subtotal', 'cost', 'profit',
        'inventory_deducted',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cost' => 'decimal:2',
        'profit' => 'decimal:2',
        'inventory_deducted' => 'boolean',
    ];

    public function menuOrder(): BelongsTo
    {
        return $this->belongsTo(MenuOrder::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}