<div style="font-family: Arial, Helvetica, sans-serif; max-width: 560px; margin: 0 auto; color: #212529;">
    <div style="background: #dc3545; color: #ffffff; padding: 16px 24px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0; font-size: 18px;">⚠ Low Stock Alert</h2>
        <div style="font-size: 13px; opacity: .9;">{{ $item->branch?->name ?? 'Branch' }}</div>
    </div>
    <div style="border: 1px solid #dee2e6; border-top: 0; padding: 24px; border-radius: 0 0 8px 8px;">
        <p style="margin-top: 0;">The following item is at or below its low stock threshold and needs restocking:</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr>
                <td style="padding: 8px 0; color: #6c757d; width: 40%;">Item</td>
                <td style="padding: 8px 0; font-weight: bold;">{{ $item->name }}</td>
            </tr>
            <tr style="border-top: 1px solid #f1f3f5;">
                <td style="padding: 8px 0; color: #6c757d;">Location</td>
                <td style="padding: 8px 0;">{{ $item->category?->location?->name ?? 'Unassigned Location' }} &gt; {{ $item->category?->name ?? 'Unassigned Category' }}</td>
            </tr>
            <tr style="border-top: 1px solid #f1f3f5;">
                <td style="padding: 8px 0; color: #6c757d;">Current Stock</td>
                <td style="padding: 8px 0; font-weight: bold; color: {{ (float) $item->quantity <= 0 ? '#dc3545' : '#fd7e14' }};">
                    {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}
                </td>
            </tr>
            <tr style="border-top: 1px solid #f1f3f5;">
                <td style="padding: 8px 0; color: #6c757d;">Threshold</td>
                <td style="padding: 8px 0;">{{ number_format((float) $item->low_stock_threshold, 2) }} {{ $item->unit }}</td>
            </tr>
        </table>
        <p style="margin-bottom: 0; color: #6c757d; font-size: 13px;">Please restock immediately. This is an automated notification from {{ config('app.name') }}.</p>
    </div>
</div>
