<?php

namespace App\Models;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class MenuOrder extends Model
{
    protected $fillable = [
        'order_number', 'branch_id', 'customer_name',
        'subtotal', 'additional_charge_label', 'additional_charge_amount', 'additional_charges',
        'regular_pax', 'pwd_pax', 'senior_pax', 'total_pax',
        'discount_type', 'discount_id_number', 'discount_name', 'discount_amount',
        'promo_campaign_id', 'promo_discount_source', 'promo_discount_code', 'promo_discount_label', 'promo_discount_amount',
        'total_vat_exempt', 'vat_rate', 'vat_amount',
        'total_amount', 'amount_paid', 'balance',
        'payment_status', 'status', 'notes', 'created_by',
        'void_reason', 'voided_by', 'voided_at', 'is_reactivated',
    ];

    protected $casts = [
        'is_reactivated' => 'boolean',
        'subtotal' => 'decimal:2',
        'additional_charge_amount' => 'decimal:2',
        'additional_charges' => 'array',
        'discount_amount' => 'decimal:2',
        'promo_discount_amount' => 'decimal:2',
        'total_vat_exempt' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (MenuOrder $order): void {
            if (! empty($order->order_number)) {
                return;
            }

            $timestamp = $order->created_at ?? now();
            do {
                $orderNumber = static::makeOrderNumber((int) $order->branch_id, $timestamp);
                $timestamp = $timestamp->copy()->addSecond();
            } while (DB::table($order->getTable())->where('order_number', $orderNumber)->exists());

            $order->order_number = $orderNumber;
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuOrderItem::class);
    }

    public function table(): HasOne
    {
        return $this->hasOne(DiningTable::class, 'current_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MenuOrderPayment::class);
    }

    public function promoCampaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'promo_campaign_id');
    }

    public function discountRedemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerDisplayName(): string
    {
        return $this->customer_name ?: 'Walk-in Customer';
    }

    public function orderNumber(): string
    {
        return $this->order_number ?: static::makeOrderNumber((int) $this->branch_id, $this->created_at ?? now());
    }

    public static function makeOrderNumber(int $branchId, $timestamp): string
    {
        return 'SALES-BR' . $branchId . '-' . $timestamp->format('Ymd-His');
    }

    public function additionalChargesList(): array
    {
        $charges = $this->additional_charges;

        if (is_array($charges) && ! empty($charges)) {
            return array_values(array_filter(array_map(function ($charge) {
                $label = trim((string) ($charge['label'] ?? ''));
                $type = in_array(($charge['type'] ?? ''), ['fixed', 'percentage'], true)
                    ? $charge['type']
                    : 'fixed';
                $value = round((float) ($charge['value'] ?? 0), 2);
                $amount = round((float) ($charge['amount'] ?? $value), 2);

                if ($label === '' || $amount <= 0) {
                    return null;
                }

                return [
                    'label' => $label,
                    'type' => $type,
                    'value' => $value,
                    'amount' => $amount,
                    'base_subtotal' => isset($charge['base_subtotal']) ? round((float) $charge['base_subtotal'], 2) : null,
                ];
            }, $charges)));
        }

        $legacyAmount = round((float) ($this->additional_charge_amount ?? 0), 2);
        if ($legacyAmount <= 0) {
            return [];
        }

        return [[
            'label' => trim((string) ($this->additional_charge_label ?: 'Additional Charge')),
            'type' => 'fixed',
            'value' => $legacyAmount,
            'amount' => $legacyAmount,
            'base_subtotal' => null,
        ]];
    }
}
