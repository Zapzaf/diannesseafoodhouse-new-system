<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class WastageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'wastage_report_id',
        'item_id',
        'scrap_name',
        'quantity_lost',
        'reason',
        'convert_to_item_id',
        'converted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity_lost' => 'decimal:2',
            'converted_quantity' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WastageReport::class, 'wastage_report_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function convertedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'convert_to_item_id');
    }
}
