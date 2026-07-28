<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing {{ $menuOrder->orderNumber() }} - Dianne's Seafood House</title>
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
        .receipt-wrap { display: flex; justify-content: center; }
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
            .receipt { width: 100%; margin: 0; padding: 4mm 4mm; box-shadow: none; }
            @page { size: 80mm auto; margin: 4mm 4mm; }
        }
    </style>
</head>
<body>
@php
    $order = $menuOrder;
    $branch = $order->branch;
    $additionalCharges = $order->additionalChargesList();
    $vatEnabled = (bool) ($branch->vat_enabled ?? true);
    $pwdEnabled = (bool) ($branch->pwd_discount_enabled ?? true);
    $seniorEnabled = (bool) ($branch->senior_discount_enabled ?? true);

    $regularPax = (int) ($order->regular_pax ?? 0);
    $pwdPax = $pwdEnabled ? (int) ($order->pwd_pax ?? 0) : 0;
    $seniorPax = $seniorEnabled ? (int) ($order->senior_pax ?? 0) : 0;
    $discountType = (string) ($order->discount_type ?? 'none');
    $discountIds = json_decode($order->discount_id_number ?? '', true) ?: [];
    $discountNames = json_decode($order->discount_name ?? '', true) ?: [];

    $lineItems = collect();
    foreach (($order->items ?? collect()) as $item) {
        $lineItems->push([
            'description' => $item->menu->name ?? 'Menu Item',
            'qty' => (int) ($item->quantity ?? 0),
            'total' => (float) ($item->subtotal ?? 0),
        ]);
    }


    $paid = round((float) ($order->payments->sum('amount') ?? 0), 2);
    $now = now()->setTimezone('Asia/Manila');
    $w = 28;
    $line = str_repeat('-', $w);

    $center = function ($text, $width = 28) {
        $text = trim((string) $text);
        if (strlen($text) >= $width) return substr($text, 0, $width);
        return str_repeat(' ', intdiv($width - strlen($text), 2)) . $text;
    };

    $formatItem = function ($name, $qty, $price) {
        $name = substr(trim((string) $name), 0, 13);
        $qtyStr = str_pad((string) (int) $qty, 3, ' ', STR_PAD_LEFT);
        $priceStr = str_pad(number_format((float) $price, 2), 9, ' ', STR_PAD_LEFT);
        return str_pad($name, 13) . ' ' . $qtyStr . ' ' . $priceStr;
    };

    $formatTotal = function ($label, $amount) {
        $label = substr(trim((string) $label), 0, 14);
        $amountStr = str_pad(number_format((float) $amount, 2), 12, ' ', STR_PAD_LEFT);
        return str_pad($label, 14) . '  ' . $amountStr;
    };
@endphp
<div class="toolbar">
    <button onclick="window.print()">Print Billing</button>
    <a href="{{ route('menu-orders.show', $order) }}">Back to Order</a>
</div>
<div class="receipt-wrap">
<div class="receipt">
<pre>{{ $center(strtoupper($branch->name ?? "DIANNE'S SEAFOOD HOUSE"), $w) }}
{{ $center("BILLING STATEMENT", $w) }}
@if(!empty($branch->address))
{{ $center($branch->address, $w) }}
@endif
@if(!empty($branch->tin_number))
{{ $center('TIN: ' . $branch->tin_number, $w) }}
@endif
{{ $line }}
ORDER#: {{ $order->orderNumber() }}
DATE:   {{ $now->format('Y-m-d') }}
TIME:   {{ $now->format('H:i:s') }} UTC+8
@if(!empty($order->customer_name))
CUSTOMER: {{ $order->customer_name }}
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
{{ $formatItem($li['description'], $li['qty'], $li['total']) }}
@endforeach
{{ $line }}
{{ $formatTotal('SUBTOTAL', (float) $order->subtotal) }}
@if((float) $order->additional_charge_amount > 0)
{{ $formatTotal('ADDL CHARGES', (float) $order->additional_charge_amount) }}
@endif
@if((float) $order->promo_discount_amount > 0)
{{ $formatTotal(strtoupper(Illuminate\Support\Str::limit($order->promo_discount_label ?? 'PROMO', 20, '')), -((float) $order->promo_discount_amount)) }}
@endif
@if((float) $order->discount_amount > 0)
{{ $formatTotal('DISCOUNT ' . strtoupper($discountType), -((float) $order->discount_amount)) }}
@endif
@if($vatEnabled && (float) $order->total_vat_exempt > 0)
{{ $formatTotal('VAT-EXEMPT', (float) $order->total_vat_exempt) }}
{{ $formatTotal('VAT (0%)', 0) }}
@elseif($vatEnabled && (float) $order->vat_rate > 0)
{{ $formatTotal('VAT (' . number_format((float) $order->vat_rate, 0) . '%)', (float) $order->vat_amount) }}
@endif
{{ $line }}
{{ $formatTotal('TOTAL', (float) $order->total_amount) }}
{{ $formatTotal('PAID', $paid) }}
{{ $formatTotal('BALANCE', (float) $order->balance) }}
{{ $line }}
@foreach(['pwd' => 'PWD', 'senior' => 'SENIOR'] as $type => $label)
@php
    $ids = $discountIds[$type] ?? [];
    $names = $discountNames[$type] ?? [];
    $count = max(count($ids), count($names));
@endphp
@if($count > 0 && (($type === 'pwd' && $pwdEnabled) || ($type === 'senior' && $seniorEnabled)))
{{ $label }} DETAILS:
@for($i = 0; $i < $count; $i++)
{{ substr(($ids[$i] ?? '-') . ' ' . ($names[$i] ?? ''), 0, $w) }}
@endfor
@endif
@endforeach
{{ $center('NOT AN OFFICIAL RECEIPT', $w) }}
</pre>
</div>
</div>
</body>
</html>
