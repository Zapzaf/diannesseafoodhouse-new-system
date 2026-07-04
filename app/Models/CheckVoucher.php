<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CheckVoucher extends Model
{
    protected $fillable = [
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * TODO(accountant): EWT is currently withheld on net_purchases (VAT-exclusive),
     * which is standard BIR practice, but the source Excel book leaves the taxable
     * base ambiguous for rows with no APV behind them. Confirm net vs. gross once
     * the PURCHASE-DISBURSEMENT-BOOK.xlsx file is reviewed and adjust here if needed.
     */
    public function applyEwt(): void
    {
        $base = (float) $this->net_purchases > 0 ? (float) $this->net_purchases : (float) $this->amount_w_vat;
        $this->ewt_amount = round($base * (float) $this->ewt_rate, 2);
        $this->amount_paid = round((float) $this->amount_w_vat - $this->ewt_amount, 2);
    }
}
