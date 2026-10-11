<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'status', 'production_date', 'finished_at', 'cancelled_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionOrder $production): void {
            $production->production_date ??= now()->toDateString();
        });
    }

    /**
     * Re-dates this production's inventory ledger rows so they follow the
     * order's own dates instead of when they were keyed in: pulling the raw
     * inputs lands on the Production Date, while everything recorded on
     * completion (finished outputs, scrap loss and scrap conversions) lands
     * on the Production Finish Date. Each row keeps its time of day so the
     * ledger order within a day is preserved.
     */
    public function syncInventoryTransactionDates(): void
    {
        $this->redateTransactions(['Pulling Items for Production: PROD-'.$this->id], $this->production_date);

        if ($this->finished_at) {
            $this->redateTransactions([
                'FROM PRODUCTION: '.$this->id,
                'SCRAP/WASTE LOSS: PRODUCTION '.$this->id,
                'FROM SCRAP/WASTE CONVERSION: PRODUCTION '.$this->id,
            ], $this->finished_at);
        }
    }

    private function redateTransactions(array $reasons, ?CarbonInterface $date): void
    {
        if (! $date) {
            return;
        }

        InventoryTransaction::query()
            ->whereIn('reason', $reasons)
            ->get()
            ->each(function (InventoryTransaction $transaction) use ($date): void {
                $time = $transaction->transaction_date ?? $transaction->created_at ?? now();
                $transaction->update(['transaction_date' => $date->copy()->setTimeFrom($time)]);
            });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(ProductionInput::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }

    public function wastageReports(): HasMany
    {
        return $this->hasMany(WastageReport::class);
    }
}
