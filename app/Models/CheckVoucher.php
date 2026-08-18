<?php

namespace App\Models;

use App\Support\VatCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CheckVoucher extends Model
{
    protected $fillable = [
        'branch_id',
        'supplier_id',
        'date',
        'cv_no',
        'reference_no',
        'purchase_voucher_id',
        'service_id',
        'advance_account_id',
        'type',
        'status',
        'particulars',
        'cost_account_id',
        'bank_account_id',
        'payment_method',
        'payee_name',
        'address',
        'si_no',
        'tin',
        'amount_w_vat',
        'vat',
        'net_purchases',
        'vat_exempt',
        'non_vat_purchase',
        'ewt_rate',
        'ewt_amount',
        'amount_paid',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount_w_vat' => 'decimal:2',
            'vat' => 'decimal:2',
            'net_purchases' => 'decimal:2',
            'vat_exempt' => 'decimal:2',
            'non_vat_purchase' => 'decimal:2',
            'ewt_rate' => 'decimal:4',
            'ewt_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function purchaseVoucher(): BelongsTo
    {
        return $this->belongsTo(PurchaseVoucher::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function advanceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'advance_account_id');
    }

    public function liquidations(): HasMany
    {
        return $this->hasMany(AdvanceLiquidation::class);
    }

    public function getLiquidatedAmountAttribute(): float
    {
        return (float) $this->liquidations()->sum('amount');
    }

    public function getOutstandingAdvanceAttribute(): float
    {
        return round((float) $this->amount_w_vat - $this->liquidated_amount, 2);
    }

    public function pettyCashVouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class);
    }

    public function costAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cost_account_id');
    }

    public function checkRegisterEntry(): HasOne
    {
        return $this->hasOne(CheckRegister::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(CheckVoucherReceipt::class);
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

    /**
     * Next sequential auto-generated disbursement reference (DIS-000001, …),
     * distinct from cv_no which is the user-entered physical check/reference number.
     */
    public static function nextDisbursementNo(): string
    {
        $last = static::query()
            ->where('reference_no', 'like', 'DIS-%')
            ->orderByDesc('id')
            ->value('reference_no');

        $sequence = $last ? (int) preg_replace('/\D/', '', substr($last, 4)) : 0;

        do {
            $candidate = 'DIS-'.str_pad(++$sequence, 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('reference_no', $candidate)->exists());

        return $candidate;
    }

    /**
     * Confirmed by accountant: EWT is always withheld on the Net Purchase amount
     * (Amount w/VAT ÷ 1.12), never on the gross VAT-inclusive amount — regardless
     * of CV type, so this doesn't fall back to amount_w_vat for APV payments or
     * PCF replenishments.
     *
     * Advances are the one exception: amount_w_vat there isn't necessarily
     * VAT-inclusive at all — a "Without VAT" advance has no VAT baked into it,
     * so forcing it through ÷ 1.12 would strip out an assumed VAT component
     * that was never there, under-withholding EWT on a non-VAT transaction.
     * For advances we use the already-computed net_purchases instead (which by
     * this point correctly holds either the VAT-exclusive split for "With VAT"
     * advances, or the full amount for "Without VAT" ones).
     *
     * amount_paid must total amount_w_vat + vat_exempt + non_vat_purchase, not just
     * amount_w_vat — a CV that's entirely VAT-exempt or non-VAT (amount_w_vat = 0)
     * was previously left with amount_paid = 0, which the Check Register then
     * displayed as ₱0.00 even though the CV carried a real amount.
     */
    public function applyEwt(): void
    {
        $netPurchase = $this->type === 'advance'
            ? (float) $this->net_purchases
            : VatCalculator::split((float) $this->amount_w_vat)['net_purchases'];

        $this->ewt_amount = round($netPurchase * (float) $this->ewt_rate, 2);
        $totalAmount = (float) $this->amount_w_vat + (float) $this->vat_exempt + (float) $this->non_vat_purchase;
        $this->amount_paid = round($totalAmount - $this->ewt_amount, 2);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}