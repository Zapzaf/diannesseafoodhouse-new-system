<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .muted { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f2f2f2; }
        .text-end { text-align: right; }
        tfoot td { font-weight: bold; background: #f7f7f7; }
    </style>
</head>
<body>
    <h1>Outstanding Payables</h1>
    <div class="muted">Generated {{ now()->format('M d, Y h:i A') }}</div>

    <table>
        <thead>
            <tr>
                <th>Module</th>
                <th>Ref #</th>
                <th>Invoice #</th>
                <th>Supplier</th>
                <th>Buyer / Payor</th>
                <th>Date</th>
                <th class="text-end">Original</th>
                <th class="text-end">Paid</th>
                <th class="text-end">Remaining</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payables as $row)
            <tr>
                <td>{{ $row->module }}</td>
                <td>{{ $row->ref_no }}</td>
                <td>{{ $row->si_no ?? '—' }}</td>
                <td>{{ $row->supplier ?? '—' }}</td>
                <td>{{ $row->party ?? '—' }}</td>
                <td>{{ $row->date->format('M d, Y') }}</td>
                <td class="text-end">{{ number_format($row->original_amount, 2) }}</td>
                <td class="text-end">{{ number_format($row->amount_paid, 2) }}</td>
                <td class="text-end">{{ number_format($row->remaining_balance, 2) }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $row->status)) }}</td>
            </tr>
            @empty
            <tr><td colspan="10">No outstanding payables found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="text-end">Total Outstanding</td>
                <td class="text-end">{{ number_format($totalOutstanding, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
