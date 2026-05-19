<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['item_id', 'branch_id', 'type', 'quantity', 'beginning_quantity', 'remaining_quantity', 'transaction_price', 'transaction_date', 'reason', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'quantity'           => 'decimal:2',
            'beginning_quantity' => 'decimal:2',
            'remaining_quantity' => 'decimal:2',
            'transaction_price'  => 'decimal:4',
            'transaction_date'   => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
