<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuOrderPayment extends Model
{
    protected $fillable = [
        'branch_id', 'menu_order_id', 'cash_shift_id', 'pos_terminal_id', 'amount', 'amount_tendered', 'change_amount', 'subtotal',
        'additional_charge_label', 'additional_charge_amount', 'additional_charges',
        'total_vat_exempt', 'total_discount', 'final_total',
        'discount_type', 'discount_amount', 'vat_amount', 'is_vat_exempt',
        'method', 'reference_number', 'or_number', 'payment_date',
        'notes', 'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'additional_charge_amount' => 'decimal:2',
        'additional_charges' => 'array',
        'total_vat_exempt' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'final_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'is_vat_exempt' => 'boolean',
        'payment_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MenuOrder::class, 'menu_order_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
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
