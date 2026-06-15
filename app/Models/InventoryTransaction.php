<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['log_id', 'item_id', 'branch_id', 'type', 'quantity', 'beginning_quantity', 'remaining_quantity', 'transaction_price', 'transaction_date', 'reason', 'status', 'notes', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (InventoryTransaction $transaction): void {
            if (blank($transaction->log_id)) {
                $transaction->log_id = static::generateUniqueLogId($transaction->transaction_date);
            }
        });
    }

    public static function generateUniqueLogId(CarbonInterface|string|null $date = null): string
    {
        $date = $date instanceof CarbonInterface
            ? $date
            : Carbon::parse($date ?? now());

        do {
            $logId = 'TRANS-'.$date->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::query()->where('log_id', $logId)->exists());

        return $logId;
    }

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
