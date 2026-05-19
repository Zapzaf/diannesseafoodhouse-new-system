<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_id', 'item_id', 'source_item_id', 'description', 'quantity', 'unit', 'price', 'allocated_to'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'price'    => 'decimal:2',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'source_item_id');
    }

    public function productionInput(): HasOne
    {
        return $this->hasOne(ProductionInput::class);
    }
}
