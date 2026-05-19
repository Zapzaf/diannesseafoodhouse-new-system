<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningTable extends Model
{
    protected $table = 'tables';

    protected $fillable = [
        'branch_id',
        'table_number',
        'capacity',
        'status',
        'current_order_id',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(MenuOrder::class, 'current_order_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->whereNull('current_order_id');
    }
}
