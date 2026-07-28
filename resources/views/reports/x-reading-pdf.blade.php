<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>X Reading</title>
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
    <h1>X Reading — Current Sales Snapshot</h1>
    <div class="subtitle">
        Business Date: {{ \Carbon\Carbon::parse($businessDate)->format('M d, Y') }}
        &middot; Branch: {{ $branchName }} &middot; Terminal: {{ $terminalName }}
        <br>Generated: {{ $generatedAt->format('M d, Y h:i A') }}
        <br><em>This is a live snapshot. It does not close the business day or reset any counters.</em>
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
            <td>Senior Citizen Discount</td><td class="text-end">₱{{ number_format($summary['senior_discount_amount'], 2) }} ({{ $summary['senior_count'] }} tx)</td>
            <td>Zero-Rated Sales</td><td class="text-end">₱{{ number_format($summary['zero_rated_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>PWD Discount</td><td class="text-end">₱{{ number_format($summary['pwd_discount_amount'], 2) }} ({{ $summary['pwd_count'] }} tx)</td>
            <td>VAT Amount</td><td class="text-end">₱{{ number_format($summary['vat_amount'], 2) }}</td>
        </tr>
        <tr>
            <td>Other Discounts</td><td class="text-end">₱{{ number_format($summary['other_discount_amount'], 2) }}</td>
            <td>Net Sales</td><td class="text-end">₱{{ number_format($summary['net_sales'], 2) }}</td>
        </tr>
        <tr>
            <td>Number of Transactions</td><td class="text-end">{{ number_format($summary['transaction_count']) }}</td>
            <td>Customers Served</td><td class="text-end">{{ number_format($summary['customers_served']) }}</td>
        </tr>
        <tr>
            <td>Refunds / Void Transactions</td><td class="text-end">{{ number_format($summary['voided_count']) }} (₱{{ number_format($summary['voided_amount'], 2) }})</td>
            <td></td><td></td>
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

    <div class="section-title">Payment Method Breakdown</div>
    <table>
        <thead><tr><th>Method</th><th class="text-end">Transactions</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            @forelse($summary['by_method'] as $row)
            <tr><td>{{ ucfirst($row->method) }}</td><td class="text-end">{{ number_format($row->transactions) }}</td><td class="text-end">₱{{ number_format($row->amount, 2) }}</td></tr>
            @empty
            <tr><td colspan="3">No payments in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Sales by Branch</div>
    <table>
        <thead><tr><th>Branch</th><th class="text-end">Transactions</th><th class="text-end">Collected</th></tr></thead>
        <tbody>
            @forelse($summary['sales_by_branch'] as $row)
            <tr><td>{{ $row->branch_name }}</td><td class="text-end">{{ number_format($row->transactions) }}</td><td class="text-end">₱{{ number_format($row->collected, 2) }}</td></tr>
            @empty
            <tr><td colspan="3">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Sales by Cashier</div>
    <table>
        <thead><tr><th>Cashier</th><th class="text-end">Transactions</th><th class="text-end">Collected</th></tr></thead>
        <tbody>
            @forelse($summary['sales_by_cashier'] as $row)
            <tr><td>{{ $row->cashier_name }}</td><td class="text-end">{{ number_format($row->transactions) }}</td><td class="text-end">₱{{ number_format($row->collected, 2) }}</td></tr>
            @empty
            <tr><td colspan="3">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
