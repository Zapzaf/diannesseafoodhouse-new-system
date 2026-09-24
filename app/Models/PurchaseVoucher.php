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
        'purchase_type',
        'vendor_id',
        'buyer',
        'si_no',
        'credit_account_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // Only cod_purchase CVs (and any unmigrated legacy rows) link through the
    // scalar purchase_voucher_id. apv_payment CVs link through allocations —
    // see checkVoucherAllocations(); don't sum this relation for amount paid.
    public function checkVouchers(): HasMany
    {
        return $this->hasMany(CheckVoucher::class);
    }

    // One row per (CV, APV) payment allocation; a CV can span several APVs.
    public function checkVoucherAllocations(): HasMany
    {
        return $this->hasMany(CheckVoucherPurchaseVoucher::class);
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
        $viaAllocations = (float) $this->checkVoucherAllocations()
            ->whereHas('checkVoucher', fn ($q) => $q->whereIn('status', ['issued', 'cleared']))
            ->sum('amount_w_vat');

        $viaLegacyCod = (float) $this->checkVouchers()
            ->where('type', 'cod_purchase')
            ->whereIn('status', ['issued', 'cleared'])
            ->sum('amount_w_vat');

        return round($viaAllocations + $viaLegacyCod, 2);
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