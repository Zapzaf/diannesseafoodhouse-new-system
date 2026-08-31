<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'delivery_date',
        'supplier_id',
        'source_branch_id',
        'destination_branch_id',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'tin',
        'address',
        'si_no',
        'amount_w_vat',
        'vat',
        'net_purchases',
        'vat_exempt',
        'non_vat_purchase',
        'ewt_rate',
        'ewt_amount',
        'approval_remarks',
        'rejection_remarks',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'approved_at' => 'datetime',
            'amount_w_vat' => 'decimal:2',
            'vat' => 'decimal:2',
            'net_purchases' => 'decimal:2',
            'vat_exempt' => 'decimal:2',
            'non_vat_purchase' => 'decimal:2',
            'ewt_rate' => 'decimal:4',
            'ewt_amount' => 'decimal:2',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Same convention as CheckVoucher::applyEwt() — EWT is withheld on the
     * Net Purchases amount (VAT stripped out), never on the VAT-exempt or
     * Non-VAT portions, and never on the gross VAT-inclusive amount.
     */
    public function applyEwt(): void
    {
        $this->ewt_amount = round((float) $this->net_purchases * (float) $this->ewt_rate, 2);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }
}
