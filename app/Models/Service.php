<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'branch_id',
        'date',
        'ref_no',
        'supplier_id',
        'payor',
        'expense_account_id',
        'si_no',
        'service_payment_type',
        'amount_w_vat',
        'vat',
        'net_purchases',
        'vat_exempt',
        'non_vat_purchase',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
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

    // A single Service bill can be settled by more than one CV (partial payments),
    // mirroring PurchaseVoucher::checkVouchers().
    public function checkVouchers(): HasMany
    {
        return $this->hasMany(CheckVoucher::class);
    }

    public function getPayableTotalAttribute(): float
    {
        return (float) $this->total_purchases;
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

    /**
     * Next sequential auto-generated Service reference (SER-000001, …).
     */
    public static function nextSerNo(): string
    {
        $last = static::query()
            ->where('ref_no', 'like', 'SER-%')
            ->orderByDesc('id')
            ->value('ref_no');

        $sequence = $last ? (int) preg_replace('/\D/', '', substr($last, 4)) : 0;

        do {
            $candidate = 'SER-'.str_pad(++$sequence, 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('ref_no', $candidate)->exists());

        return $candidate;
    }
}
