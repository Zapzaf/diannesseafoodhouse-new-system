<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseVoucher extends Model
{
    protected $fillable = [
        'branch_id',
        'date',
        'apv_no',
        'vendor_id',
        'si_no',
        'credit_account_id',
        'status',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseVoucherItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // A single APV can be settled by more than one CV (partial payments),
    // so this is hasMany rather than hasOne despite the spec doc's shorthand.
    public function checkVouchers(): HasMany
    {
        return $this->hasMany(CheckVoucher::class);
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum('total_purchases');
    }

    /**
     * The gross amount owed to the vendor (VAT-inclusive), used to drive the
     * unpaid/partially_paid/paid state machine.
     */
    public function getPayableTotalAttribute(): float
    {
        return (float) $this->items->sum(fn (PurchaseVoucherItem $item) => $item->payable_amount);
    }

    public function getAmountPaidAttribute(): float
    {
        return (float) $this->checkVouchers()->whereIn('status', ['issued', 'cleared'])->sum('amount_w_vat');
    }

    public function recomputeStatus(): void
    {
        $payable = $this->payable_total;
        $paid = $this->amount_paid;

        $status = 'unpaid';
        if ($payable > 0 && $paid >= $payable) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partially_paid';
        }

        $this->update(['status' => $status]);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Next sequential auto-generated APV number (APV-000001, APV-000002, …).
     */
    public static function nextApvNo(): string
    {
        $last = static::query()
            ->where('apv_no', 'like', 'APV-%')
            ->orderByDesc('id')
            ->value('apv_no');

        $sequence = $last ? (int) preg_replace('/\D/', '', substr($last, 4)) : 0;

        do {
            $candidate = 'APV-'.str_pad(++$sequence, 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('apv_no', $candidate)->exists());

        return $candidate;
    }
}