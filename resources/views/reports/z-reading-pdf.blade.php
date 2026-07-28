<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reading->reading_number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; }
        .receipt { max-width: 420px; margin: 0 auto; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
        .center { text-align: center; }
        .subtitle { color: #333; margin-bottom: 10px; text-align: center; }
        hr { border: none; border-top: 1px dashed #333; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; }
        .text-end { text-align: right; }
        .section-title { font-weight: bold; margin: 10px 0 4px; }
        .grand { font-weight: bold; font-size: 13px; }
        .locked { text-align: center; font-weight: bold; margin-top: 10px; border: 1px solid #333; padding: 4px; }
    </style>
</head>
<body>
    <div class="receipt">
        <h1>Z READING</h1>
        <div class="subtitle">End-of-Day Closing Report</div>
        <div class="center">{{ $reading->branch->name ?? '' }}</div>
        <div class="center">Terminal: {{ $reading->terminal->name ?? '' }} ({{ $reading->terminal->code ?? '' }})</div>
        <hr>
        <table>
            <tr><td>Z Reading No.</td><td class="text-end fw-bold">{{ $reading->reading_number }}</td></tr>
            <tr><td>Business Date</td><td class="text-end">{{ $reading->business_date->format('M d, Y') }}</td></tr>
            <tr><td>Date/Time Closed</td><td class="text-end">{{ $reading->generated_at->format('M d, Y h:i A') }}</td></tr>
            <tr><td>Operator</td><td class="text-end">{{ $reading->generatedBy->name ?? '—' }}</td></tr>
        </table>
        <hr>

        <div class="section-title">Sales Summary</div>
        <table>
            <tr><td>Gross Sales</td><td class="text-end">₱{{ number_format($summary['gross_sales'], 2) }}</td></tr>
            <tr><td>Less: PWD/Senior Discounts</td><td class="text-end">₱{{ number_format($summary['total_discount'], 2) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Senior Citizen ({{ $summary['senior_count'] }})</td><td class="text-end">₱{{ number_format($summary['senior_discount_amount'], 2) }}</td></tr>
            <tr><td>&nbsp;&nbsp;PWD ({{ $summary['pwd_count'] }})</td><td class="text-end">₱{{ number_format($summary['pwd_discount_amount'], 2) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Other</td><td class="text-end">₱{{ number_format($summary['other_discount_amount'], 2) }}</td></tr>
            <tr><td>Less: Promotional Discounts</td><td class="text-end">₱{{ number_format($summary['promo_discount_amount'] ?? 0, 2) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Coupon</td><td class="text-end">₱{{ number_format($summary['promo_coupon_discount_amount'] ?? 0, 2) }}</td></tr>
            <tr><td>&nbsp;&nbsp;Manual</td><td class="text-end">₱{{ number_format($summary['promo_manual_discount_amount'] ?? 0, 2) }}</td></tr>
            <tr><td class="grand">Net Sales</td><td class="text-end grand">₱{{ number_format($summary['net_sales'], 2) }}</td></tr>
        </table>

        <div class="section-title">VAT Summary</div>
        <table>
            <tr><td>VATable Sales</td><td class="text-end">₱{{ number_format($summary['vatable_sales'], 2) }}</td></tr>
            <tr><td>VAT-Exempt Sales</td><td class="text-end">₱{{ number_format($summary['vat_exempt_sales'], 2) }}</td></tr>
            <tr><td>Zero-Rated Sales</td><td class="text-end">₱{{ number_format($summary['zero_rated_sales'], 2) }}</td></tr>
            <tr><td>VAT Amount</td><td class="text-end">₱{{ number_format($summary['vat_amount'], 2) }}</td></tr>
        </table>

        <div class="section-title">Transaction Counts</div>
        <table>
            <tr><td>Number of Transactions</td><td class="text-end">{{ number_format($summary['transaction_count']) }}</td></tr>
            <tr><td>Customers Served</td><td class="text-end">{{ number_format($summary['customers_served']) }}</td></tr>
            <tr><td>Discounted Transactions (Promo)</td><td class="text-end">{{ number_format($summary['promo_discount_count'] ?? 0) }}</td></tr>
            <tr><td>Refunds / Void Transactions</td><td class="text-end">{{ number_format($summary['voided_count']) }} (₱{{ number_format($summary['voided_amount'], 2) }})</td></tr>
        </table>

        <div class="section-title">Payment Method Breakdown</div>
        <table>
            @foreach($summary['by_method'] as $row)
            <tr>
                <td>{{ ucfirst(is_array($row) ? $row['method'] : $row->method) }} ({{ is_array($row) ? $row['transactions'] : $row->transactions }})</td>
                <td class="text-end">₱{{ number_format(is_array($row) ? $row['amount'] : $row->amount, 2) }}</td>
            </tr>
            @endforeach
        </table>

        <hr>
        <div class="locked">
            {{ $reading->status === 'voided' ? '*** VOIDED ***' : '*** LOCKED — OFFICIAL Z READING ***' }}
        </div>
        @if($reading->status === 'voided')
        <div class="center" style="margin-top:6px;">Voided by {{ $reading->voidedBy->name ?? '—' }} on {{ $reading->voided_at?->format('M d, Y h:i A') }}<br>Reason: {{ $reading->void_reason }}</div>
        @endif
    </div>
</body>
</html>
