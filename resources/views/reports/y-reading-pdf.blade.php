<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Y Reading</title>
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
    </style>
</head>
<body>
    <h1>Y Reading — Shift Summary</h1>
    <div class="subtitle">
        Cashier: {{ $shift->cashier->name ?? '—' }} &middot; Terminal: {{ $shift->terminal->name ?? '—' }} ({{ $shift->terminal->code ?? '—' }}) &middot; Branch: {{ $shift->branch->name ?? '—' }}
        <br>Shift Start: {{ $shift->opened_at->format('M d, Y h:i A') }} &middot; Shift End: {{ $shift->closed_at?->format('M d, Y h:i A') ?? 'Still open' }}
        <br>Generated: {{ $generatedAt->format('M d, Y h:i A') }}
        <br><em>This reading does not close the business day or reset any counters.</em>
    </div>

    <div class="section-title">Cash Reconciliation</div>
    <table class="kpi-table">
        <tr>
            <td>Opening Cash Float</td><td class="text-end">₱{{ number_format($shift->opening_float, 2) }}</td>
            <td>Expected Cash on Hand</td><td class="text-end">₱{{ number_format($expectedCash, 2) }}</td>
        </tr>
        @if(!$shift->isOpen())
        <tr>
            <td>Counted Cash</td><td class="text-end">₱{{ number_format($shift->closing_cash_counted, 2) }}</td>
            <td>Cash Variance</td><td class="text-end">₱{{ number_format($shift->cash_variance, 2) }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">Sales Summary</div>
    <table class="kpi-table">
        <tr>
            <td>Gross Sales</td><td class="text-end">₱{{ number_format($summary['gross_sales'], 2) }}</td>
            <td>VATable Sales</td><td class="text-end">₱{{ number_format($summary['vatable_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>Total Discounts</td><td class="text-end">₱{{ number_format($summary['total_discount'], 2) }}</td>
            <td>VAT-Exempt Sales</td><td class="text-end">₱{{ number_format($summary['vat_exempt_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>Senior Citizen Discount</td><td class="text-end">₱{{ number_format($summary['senior_discount_amount'], 2) }} ({{ $summary['senior_count'] }} tx)</td>
            <td>VAT Amount</td><td class="text-end">₱{{ number_format($summary['vat_amount'], 2) }}</td>
        </tr>
        <tr>
            <td>PWD Discount</td><td class="text-end">₱{{ number_format($summary['pwd_discount_amount'], 2) }} ({{ $summary['pwd_count'] }} tx)</td>
            <td>Net Sales</td><td class="text-end">₱{{ number_format($summary['net_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>Number of Transactions</td><td class="text-end">{{ number_format($summary['transaction_count']) }}</td>
            <td>Customers Served</td><td class="text-end">{{ number_format($summary['customers_served']) }}</td>
        </tr>
    </table>

    <div class="section-title">Payment Method Breakdown</div>
    <table>
        <thead><tr><th>Method</th><th class="text-end">Transactions</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            @forelse($summary['by_method'] as $row)
            <tr><td>{{ ucfirst($row->method) }}</td><td class="text-end">{{ number_format($row->transactions) }}</td><td class="text-end">₱{{ number_format($row->amount, 2) }}</td></tr>
            @empty
            <tr><td colspan="3">No payments this shift.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
