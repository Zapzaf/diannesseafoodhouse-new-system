<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    protected $fillable = [
        'branch_id', 'pos_terminal_id', 'cashier_id',
        'opening_float', 'opened_at', 'opened_by',
        'status', 'closing_cash_counted', 'expected_cash', 'cash_variance',
        'closed_at', 'closed_by', 'notes',
    ];

    protected $casts = [
        'opening_float' => 'decimal:2',
        'closing_cash_counted' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_variance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MenuOrderPayment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function cashIn(): float
    {
        return (float) $this->movements()->where('type', 'in')->sum('amount');
    }

    public function cashOut(): float
    {
        return (float) $this->movements()->where('type', 'out')->sum('amount');
    }

    public function cashSales(): float
    {
        return (float) $this->payments()->where('method', 'cash')->sum('amount');
    }

    public function computeExpectedCash(): float
    {
        return round(
            (float) $this->opening_float + $this->cashSales() + $this->cashIn() - $this->cashOut(),
            2
        );
    }
}
