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
}
