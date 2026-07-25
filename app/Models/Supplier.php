<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'company_name',
        'business_name',
        'owner_name',
        'contact_person',
        'phone',
        'email',
        'address',
        'tin',
        'is_vat_registered',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_vat_registered' => 'boolean',
        ];
    }

    /**
     * Keep `name` (used everywhere suppliers are displayed — POs, CVs, reports,
     * lookups) in sync with the type-specific name field, so every existing
     * call site automatically shows the right name without being touched.
     */
    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $supplier->name = $supplier->type === 'sole_proprietorship'
                ? ($supplier->business_name ?: $supplier->name)
                : ($supplier->company_name ?: $supplier->name);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function purchaseVouchers(): HasMany
    {
        return $this->hasMany(PurchaseVoucher::class, 'vendor_id');
    }

    public function checkVouchers(): HasMany
    {
        return $this->hasMany(CheckVoucher::class);
    }

    public function pettyCashVouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class);
    }

    /**
     * Whether this supplier has purchase/delivery history that would be
     * orphaned (vendor_id/supplier_id set to null) if the record were deleted.
     */
    public function hasLinkedRecords(): bool
    {
        return $this->deliveries()->exists()
            || $this->purchaseVouchers()->exists()
            || $this->checkVouchers()->exists()
            || $this->pettyCashVouchers()->exists();
    }
}
