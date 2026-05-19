<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $payment->or_number }} - Dianne's Seafood House</title>
    <style>
        :root { --receipt-width: 80mm; }
        * { box-sizing: border-box; }
        html { font-size: 14px; }
        body {
            margin: 0;
            padding: 20px 0;
            background: #b0b0b0;
            color: #000;
            font-family: ui-monospace, "Courier New", monospace;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        .toolbar {
            background: #fff;
            padding: 8px;
            text-align: center;
            border-bottom: 1px solid #ddd;
            margin-bottom: 16px;
        }
        .toolbar button,
        .toolbar a {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #999;
            background: #fff;
            color: #000;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            margin: 0 4px;
        }
        .receipt-wrap {
            display: flex;
            justify-content: center;
        }
        .receipt {
            width: var(--receipt-width);
            background: #fff;
            padding: 6mm 5mm;
            box-shadow: 0 2px 8px rgba(0,0,0,0.35);
        }
        .receipt pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: inherit;
            font-size: inherit;
        }
        @media print {
            .toolbar { display: none !important; }
            body { margin: 0; padding: 0; background: #fff; }
            .receipt-wrap { display: block; }
            .receipt {
                width: 100%;
                margin: 0;
                padding: 4mm 4mm;
                box-shadow: none;
            }
            @page { size: 80mm auto; margin: 4mm 4mm; }
        }
    </style>
</head>
<body>
@php
    use Illuminate\Support\Carbon;

    $checkin       = $payment->checkin ?? null;
    $menuItems     = $checkin->checkinItems ?? collect();
    $customCharges = $checkin->customCharges ?? collect();

    // Customer / pax info
    $customerType  = (string) ($checkin->customer_type ?? '');
    $isVATExempt   = in_array(strtoupper($customerType), ['PWD', 'SENIOR', 'SENIOR CITIZEN']);
    $discountType  = $customerType;

    // PAX breakdown (use order pax fields if available, fallback to checkin)
    $order      = $payment->order ?? null;
    $regularPax = (int) ($order->regular_pax ?? $checkin->regular_pax ?? 0);
    $pwdPax     = (int) ($order->pwd_pax    ?? $checkin->pwd_pax    ?? 0);
    $seniorPax  = (int) ($order->senior_pax ?? $checkin->senior_pax ?? 0);

    if ($customCharges->isEmpty() && (float) ($checkin->custom_charge_amount ?? 0) > 0) {
        $customCharges = collect([
            (object) [
                'description' => trim((string) ($checkin->custom_charge_label ?: 'Custom Charge')),
                'amount'      => (float) ($checkin->custom_charge_amount ?? 0),
                'created_at'  => $payment->payment_date,
            ],
        ]);
    }

    $lineItems = collect([
        [
            'date'        => optional($checkin->actual_check_in)->format('M d, Y'),
            'description' => 'Room Charge',
            'qty'         => max(1, (int) ($checkin->nights ?? 1)),
            'amount'      => (float) ($checkin->room_rate ?? 0),
            'total'       => (float) ($checkin->subtotal ?? 0),
        ],
    ]);

    foreach ($menuItems as $item) {
        $lineItems->push([
            'date'        => optional($item->created_at)->format('M d, Y') ?: optional($payment->payment_date)->format('M d, Y'),
            'description' => ($item->menu->name ?? 'Item'),
            'qty'         => (int) ($item->quantity ?? 0),
            'amount'      => (float) ($item->unit_price ?? 0),
            'total'       => (float) ($item->subtotal ?? 0),
        ]);
    }

    foreach ($customCharges as $charge) {
        $chargeAmount = round((float) ($charge->amount ?? 0), 2);
        if ($chargeAmount <= 0) continue;
        $lineItems->push([
            'date'        => optional($charge->created_at ?? $payment->payment_date)->format('M d, Y'),
            'description' => trim((string) ($charge->description ?: 'Charge')),
            'qty'         => 1,
            'amount'      => $chargeAmount,
            'total'       => $chargeAmount,
        ]);
    }

    $billingInstruction = trim((string) ($payment->notes ?: $checkin->notes ?? ''));
    $money = fn (float $v): string => number_format($v, 2);

    // Totals
    $subtotal           = (float) ($checkin->total_amount ?? 0);
    $discount           = (float) ($checkin->discount_amount ?? 0);
    $discountedSubtotal = $subtotal - $discount;

    $vatRate   = $isVATExempt ? 0 : (float) ($checkin->vat_rate ?? 12);
    $vatAmount = $isVATExempt ? 0 : round($discountedSubtotal * ($vatRate / 100), 2);
    $total     = round($discountedSubtotal + $vatAmount, 2);

    // Amount paid & change
    $amountPaid = (float) (!empty($payment->amount_tendered) ? $payment->amount_tendered : ($payment->amount ?? 0));
    $finalTotal = (float) ($payment->final_total ?? $total);
    $change     = (float) (isset($payment->change_amount) && $payment->change_amount > 0 ? $payment->change_amount : max(0.0, $amountPaid - $finalTotal));

    // UTC+8 datetime
    $paymentDateTime = $payment->payment_date ?? $payment->created_at;
    $phDateTime = $paymentDateTime
        ? $paymentDateTime->copy()->setTimezone('Asia/Manila')
        : now()->setTimezone('Asia/Manila');

    $w    = 28;
    $line = str_repeat('-', $w);

    $center = function ($text, $width = 28) {
        $text = trim((string) $text);
        if (strlen($text) >= $width) return substr($text, 0, $width);
        $pad = intdiv($width - strlen($text), 2);
        return str_repeat(' ', $pad) . $text;
    };

    // Layout: name(13) + space(1) + qty(3) + space(1) + price(9) = 27
    $formatItem = function ($name, $qty, $price, $width = 28) {
        $name     = substr(trim($name), 0, 13);
        $qtyStr   = str_pad((string)(int)$qty, 3, ' ', STR_PAD_LEFT);
        $priceStr = str_pad(number_format((float)$price, 2), 9, ' ', STR_PAD_LEFT);
        return str_pad($name, 13) . ' ' . $qtyStr . ' ' . $priceStr;
    };

    // Layout: label(14) + space(2) + amount(12) = 28
    $formatTotal = function ($label, $amount, $width = 28) {
        $label     = substr(trim($label), 0, 14);
        $amountStr = str_pad(number_format((float)$amount, 2), 12, ' ', STR_PAD_LEFT);
        return str_pad($label, 14) . '  ' . $amountStr;
    };
@endphp

<div class="toolbar">
    <button onclick="window.print()">Print Receipt</button>
    <a href="{{ route('payments.show', $payment) }}">Back to Payment</a>
</div>
<div class="receipt-wrap">
<div class="receipt">
<pre>{{ $center(strtoupper($payment->branch->name ?? "DIANNE'S SEAFOOD HOUSE"), $w) }}
@if(!empty($payment->branch->address))
{{ $center($payment->branch->address, $w) }}
@endif
@if(!empty($payment->branch->tin_number))
{{ $center('TIN: ' . $payment->branch->tin_number, $w) }}
@endif
{{ $line }}
OR#:  {{ $payment->or_number ?? 'N/A' }}
DATE: {{ $phDateTime->format('Y-m-d') }}
TIME: {{ $phDateTime->format('H:i:s') }} UTC+8
@if($customerType)
CUSTOMER TYPE: {{ strtoupper($customerType) }}@if($isVATExempt) [VAT EXEMPT]@endif
@endif
{{ $line }}
@if($regularPax > 0 || $pwdPax > 0 || $seniorPax > 0)
PAX:
@if($regularPax > 0)
  {{ $regularPax }}x Regular
@endif
@if($pwdPax > 0)
  {{ $pwdPax }}x PWD
@endif
@if($seniorPax > 0)
  {{ $seniorPax }}x Senior Citizen
@endif
{{ $line }}
@endif
DESC          QTY    AMOUNT
{{ $line }}
@foreach($lineItems as $li)
{{ $formatItem($li['description'], $li['qty'], $li['total'], $w) }}
@endforeach
{{ $line }}
{{ $formatTotal('SUBTOTAL', $subtotal, $w) }}
@if($discount > 0)
{{ $formatTotal('DISCOUNT (' . strtoupper($discountType ?: 'GENERAL') . ')', -$discount, $w) }}
@endif
@if($isVATExempt)
{{ $formatTotal('VAT-EXEMPT SALE', $discountedSubtotal, $w) }}
{{ $formatTotal('VAT (0%)', 0, $w) }}
@else
@if($vatRate > 0)
{{ $formatTotal('VAT (' . number_format($vatRate, 0) . '%)', $vatAmount, $w) }}
@endif
@endif
{{ $line }}
{{ $formatTotal('TOTAL', $finalTotal, $w) }}
{{ $line }}
{{ $formatTotal('TENDERED', $amountPaid, $w) }}
{{ $formatTotal('CHANGE', $change, $w) }}
{{ $line }}
@if(!empty($payment->method))
METHOD: {{ strtoupper($payment->method) }}
@endif
@if(!empty($payment->reference_number))
REF#: {{ $payment->reference_number }}
@endif
@if($billingInstruction !== '')
NOTE: {{ substr($billingInstruction, 0, 36) }}
@endif
@if(!empty($payment->receivedBy?->name))
CASHIER: {{ $payment->receivedBy->name }}
@endif
{{ $line }}
{{ $center('THANK YOU FOR YOUR BUSINESS!', $w) }}
{{ $center('Please come again.', $w) }}
</pre>
</div>
</div>
</body>
</html>
