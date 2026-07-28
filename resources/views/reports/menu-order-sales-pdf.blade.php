<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Menu Order Sales Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .subtitle { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background-color: #2d3748; color: #fff; }
        .text-end { text-align: right; }
        .kpi-table td { border: none; padding: 3px 8px 3px 0; }
        .section-title { font-size: 13px; font-weight: bold; margin: 14px 0 6px; border-bottom: 1px solid #333; padding-bottom: 2px; }
        .totals-row td { font-weight: bold; background-color: #edf2f7; }
    </style>
</head>
<body>
    <h1>Menu Order Sales Report</h1>
    <div class="subtitle">
        Period: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
        &middot; Branch: {{ $branchName }}
        @if($method) &middot; Method: {{ ucfirst($method) }} @endif
        <br>Generated: {{ now()->format('M d, Y h:i A') }}
    </div>

    <div class="section-title">Summary</div>
    <table class="kpi-table">
        <tr>
            <td>Gross Sales</td><td class="text-end">₱{{ number_format($summary['gross_sales'], 2) }}</td>
            <td>VATable Sales</td><td class="text-end">₱{{ number_format($summary['vatable_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>PWD/Senior Discounts</td><td class="text-end">₱{{ number_format($summary['total_discount'], 2) }}</td>
            <td>VAT-Exempt Sales</td><td class="text-end">₱{{ number_format($summary['vat_exempt_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>VAT Amount</td><td class="text-end">₱{{ number_format($summary['vat_amount'], 2) }}</td>
            <td>Zero-Rated Sales</td><td class="text-end">₱{{ number_format($summary['zero_rated_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>Net Sales</td><td class="text-end">₱{{ number_format($summary['net_sales'], 2) }}</td>
            <td>Number of Transactions</td><td class="text-end">{{ number_format($summary['transaction_count']) }}</td>
        </tr>
        <tr>
            <td>Customers Served</td><td class="text-end">{{ number_format($summary['customers_served']) }}</td>
            <td>Refunds / Void Transactions</td><td class="text-end">{{ number_format($summary['voided_count']) }} (₱{{ number_format($summary['voided_amount'], 2) }})</td>
        </tr>
        <tr>
            <td>Senior Citizen Discounts</td><td class="text-end">{{ number_format($summary['senior_count']) }} (₱{{ number_format($summary['senior_discount_amount'], 2) }})</td>
            <td>PWD Discounts</td><td class="text-end">{{ number_format($summary['pwd_count']) }} (₱{{ number_format($summary['pwd_discount_amount'], 2) }})</td>
        </tr>
    </table>

    <div class="section-title">Promotional Discounts (separate from PWD/Senior)</div>
    <table class="kpi-table">
        <tr>
            <td>Total Promotional Discounts</td><td class="text-end">₱{{ number_format($summary['promo_discount_amount'] ?? 0, 2) }}</td>
            <td>Discounted Transactions</td><td class="text-end">{{ number_format($summary['promo_discount_count'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Coupon Discounts</td><td class="text-end">₱{{ number_format($summary['promo_coupon_discount_amount'] ?? 0, 2) }}</td>
            <td>Manual Promotional Discounts</td><td class="text-end">₱{{ number_format($summary['promo_manual_discount_amount'] ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Net Sales After Promo Discounts</td><td class="text-end">₱{{ number_format($summary['net_sales_after_promo_discount'] ?? 0, 2) }}</td>
            <td></td><td></td>
        </tr>
    </table>

    <div class="section-title">Total Sales per Branch</div>
    <table>
        <thead>
            <tr><th>Branch</th><th class="text-end">Transactions</th><th class="text-end">Gross</th><th class="text-end">Discounts</th><th class="text-end">Collected</th></tr>
        </thead>
        <tbody>
            @forelse($summary['sales_by_branch'] as $row)
            <tr>
                <td>{{ $row->branch_name }}</td>
                <td class="text-end">{{ number_format($row->transactions) }}</td>
                <td class="text-end">₱{{ number_format($row->gross, 2) }}</td>
                <td class="text-end">₱{{ number_format($row->discount, 2) }}</td>
                <td class="text-end">₱{{ number_format($row->collected, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Payment Method Breakdown</div>
    <table>
        <thead>
            <tr><th>Method</th><th class="text-end">Transactions</th><th class="text-end">Amount</th></tr>
        </thead>
        <tbody>
            @forelse($summary['by_method'] as $row)
            <tr>
                <td>{{ ucfirst($row->method) }}</td>
                <td class="text-end">{{ number_format($row->transactions) }}</td>
                <td class="text-end">₱{{ number_format($row->amount, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3">No payments in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Transactions (Official Receipts)</div>
    <table>
        <thead>
            <tr>
                <th>OR #</th><th>Date</th><th>Branch</th><th>Order #</th><th>Customer</th>
                <th>Method</th><th>PWD/Senior</th><th>Promo</th><th class="text-end">Gross</th><th class="text-end">VAT</th><th class="text-end">Net</th><th>Received By</th>
            </tr>
        </thead>
        <tbody>
            @php $totalNet = 0; @endphp
            @forelse($payments as $payment)
            @php $totalNet += (float) $payment->amount; @endphp
            <tr>
                <td>{{ $payment->or_number ?? '—' }}</td>
                <td>{{ optional($payment->payment_date)->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</td>
                <td>{{ $payment->branch?->name ?? '—' }}</td>
                <td>{{ $payment->order?->order_number ?? '—' }}</td>
                <td>{{ $payment->order?->customer_name ?: 'Walk-in' }}</td>
                <td>{{ ucfirst($payment->method) }}</td>
                <td>
                    @if($payment->discount_type && $payment->discount_type !== 'none')
                        {{ ucfirst($payment->discount_type) }} (₱{{ number_format($payment->discount_amount, 2) }})
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($payment->promo_discount_amount > 0)
                        {{ $payment->promo_discount_label ?? ucfirst($payment->promo_discount_source) }} (₱{{ number_format($payment->promo_discount_amount, 2) }})
                    @else
                        —
                    @endif
                </td>
                <td class="text-end">₱{{ number_format($payment->subtotal + $payment->additional_charge_amount, 2) }}</td>
                <td class="text-end">₱{{ number_format($payment->vat_amount, 2) }}</td>
                <td class="text-end">₱{{ number_format($payment->amount, 2) }}</td>
                <td>{{ $payment->receivedBy?->name ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="12">No transactions in this period.</td></tr>
            @endforelse
            @if($payments->isNotEmpty())
            <tr class="totals-row">
                <td colspan="10">TOTAL NET SALES</td>
                <td class="text-end">₱{{ number_format($totalNet, 2) }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
