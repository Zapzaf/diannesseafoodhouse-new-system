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
        'purchase_voucher_id',
        'type',
        'status',
        'particulars',
        'cost_account_id',
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

    /**
     * Confirmed by accountant: EWT is always withheld on the Net Purchase amount
     * (Amount w/VAT ÷ 1.12), never on the gross VAT-inclusive amount — regardless
     * of CV type, so this doesn't fall back to amount_w_vat for APV payments or
     * PCF replenishments.
     *
     * amount_paid must total amount_w_vat + vat_exempt + non_vat_purchase, not just
     * amount_w_vat — a CV that's entirely VAT-exempt or non-VAT (amount_w_vat = 0)
     * was previously left with amount_paid = 0, which the Check Register then
     * displayed as ₱0.00 even though the CV carried a real amount.
     */
    public function applyEwt(): void
    {
        $netPurchase = VatCalculator::split((float) $this->amount_w_vat)['net_purchases'];
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