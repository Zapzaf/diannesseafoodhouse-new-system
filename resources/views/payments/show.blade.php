@extends('layouts.app')

@section('page_title', 'Payment #' . $payment->id . ' - Dianne\'s Seafood House')

@section('content')
    <x-page-header :title="'Payment #' . $payment->id" subtitle="Recorded payment details and balance impact" icon="receipt">
        @if($payment->order)
        <a href="{{ route('menu-orders.payments.receipt', $payment) }}" target="_blank" class="btn btn-primary">
            <i data-lucide="printer" class="me-1"></i> Print Receipt
        </a>
        <a href="{{ route('menu-orders.show', $payment->order) }}" class="btn btn-outline-primary">
            <i data-lucide="shopping-bag" class="me-1"></i> View Order
        </a>
        @endif
        <a href="{{ route('payments.index') }}" class="btn btn-outline-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Payments
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        @php
            $additionalCharges = $payment->additionalChargesList();
        @endphp

        @if($payment->is_vat_exempt || (float) $payment->total_discount > 0)
        <div class="alert alert-warning d-flex align-items-center mb-3">
            <i data-lucide="tag" class="me-2"></i>
            <span><strong>VAT EXEMPT / DISCOUNTED SALE</strong> - Per-person computation applied for eligible guests.</span>
        </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                        Invoice
                        @if($payment->or_number)
                        <span class="badge bg-secondary fs-6 text-white">OR #{{ $payment->or_number }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-5">Date</dt><dd class="col-sm-7">{{ $payment->payment_date?->format('M d, Y') }}</dd>
                            <dt class="col-sm-5">Customer</dt><dd class="col-sm-7">{{ $payment->order?->customerDisplayName() ?? '-' }}</dd>
                            <dt class="col-sm-5">Order</dt><dd class="col-sm-7">{{ $payment->order?->orderNumber() ?? 'Order #' . $payment->menu_order_id }}</dd>
                            <dt class="col-sm-5 mt-2">Amount Applied</dt><dd class="col-sm-7 mt-2 fw-bold text-success fs-5">₱{{ number_format((float) $payment->amount, 2) }}</dd>
                            <dt class="col-sm-5">Amount Tendered</dt><dd class="col-sm-7">₱{{ number_format((float) $payment->amount_tendered, 2) }}</dd>
                            <dt class="col-sm-5">Change</dt><dd class="col-sm-7 text-danger">₱{{ number_format((float) $payment->change_amount, 2) }}</dd>
                            <dt class="col-sm-5 mt-2">Method</dt><dd class="col-sm-7 mt-2"><span class="badge bg-secondary text-white">{{ ucfirst((string) $payment->method) }}</span></dd>
                            <dt class="col-sm-5">Reference</dt><dd class="col-sm-7">{{ $payment->reference_number ?? '-' }}</dd>
                            <dt class="col-sm-5">Notes</dt><dd class="col-sm-7">{{ $payment->notes ?? '-' }}</dd>
                            <dt class="col-sm-5">Branch</dt><dd class="col-sm-7">{{ $payment->branch?->name ?? '-' }}</dd>
                            <dt class="col-sm-5">Received By</dt><dd class="col-sm-7">{{ $payment->receivedBy?->name ?? '-' }}</dd>
                        </dl>
                        <hr>

                        <h6 class="fw-semibold text-muted mb-2">Billing Breakdown</h6>
                        <div class="d-flex justify-content-between">
                            <span>Subtotal</span>
                            <span>₱{{ number_format((float) $payment->subtotal, 2) }}</span>
                        </div>
                        @if(!empty($additionalCharges))
                        <div class="d-flex justify-content-between">
                            <span>Additional Charges</span>
                            <span>₱{{ number_format((float) $payment->additional_charge_amount, 2) }}</span>
                        </div>
                        @foreach($additionalCharges as $charge)
                        <div class="d-flex justify-content-between text-muted small">
                            <span>
                                {{ $charge['label'] }}
                                @if(($charge['type'] ?? 'fixed') === 'percentage')
                                    ({{ number_format((float) ($charge['value'] ?? 0), 2) }}%)
                                @endif
                            </span>
                            <span>₱{{ number_format((float) ($charge['amount'] ?? 0), 2) }}</span>
                        </div>
                        @endforeach
                        @endif
                        <div class="d-flex justify-content-between">
                            <span>VAT ({{ number_format((float) ($payment->order?->vat_rate ?? 12), 0) }}%)</span>
                            <span>₱{{ number_format((float) $payment->vat_amount, 2) }}</span>
                        </div>
                        @if((float) $payment->total_discount > 0)
                        <div class="d-flex justify-content-between text-muted small">
                            <span>VAT Exempt Sales</span>
                            <span>₱{{ number_format((float) $payment->total_vat_exempt, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-warning">
                            <span>Less: Total Guest Discounts</span>
                            <span>- ₱{{ number_format((float) $payment->total_discount, 2) }}</span>
                        </div>
                        @endif
                        <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-1">
                            <span>Total Due</span>
                            <span>₱{{ number_format((float) $payment->final_total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                @if($payment->order)
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                        Payment History for {{ $payment->order->orderNumber() }}
                        <span class="badge bg-light text-dark">{{ $payment->order->payments->count() }} payment(s)</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Applied</th>
                                        <th>Tendered</th>
                                        <th>Change</th>
                                        <th>OR No.</th>
                                        <th>Ref</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payment->order->payments->sortByDesc('payment_date') as $entry)
                                    <tr class="{{ $entry->id === $payment->id ? 'table-warning' : '' }}">
                                        <td>{{ $entry->payment_date?->format('M d, Y') }}</td>
                                        <td><span class="badge bg-secondary text-white">{{ ucfirst((string) $entry->method) }}</span></td>
                                        <td class="text-success fw-semibold">₱{{ number_format((float) $entry->amount, 2) }}</td>
                                        <td>₱{{ number_format((float) $entry->amount_tendered, 2) }}</td>
                                        <td class="text-danger">₱{{ number_format((float) $entry->change_amount, 2) }}</td>
                                        <td>{{ $entry->or_number ?: '-' }}</td>
                                        <td>{{ $entry->reference_number ?: '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">No payments found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Order Summary</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td>Order Status</td><td><span class="badge-status badge-{{ strtolower((string) ($payment->order?->status ?? 'unknown')) }}">{{ ucfirst((string) ($payment->order?->status ?? 'N/A')) }}</span></td></tr>
                                <tr><td>Payment Status</td><td><span class="badge-status badge-{{ strtolower((string) ($payment->order?->payment_status ?? 'unpaid')) }}">{{ ucfirst((string) ($payment->order?->payment_status ?? 'N/A')) }}</span></td></tr>
                                <tr><td>Total Amount</td><td class="text-end">₱{{ number_format((float) ($payment->order?->total_amount ?? 0), 2) }}</td></tr>
                                <tr class="text-success"><td>Amount Paid</td><td class="text-end">₱{{ number_format((float) ($payment->order?->amount_paid ?? 0), 2) }}</td></tr>
                                <tr class="{{ ((float) ($payment->order?->balance ?? 0)) > 0 ? 'text-danger fw-bold' : 'text-success' }}"><td>Balance</td><td class="text-end">₱{{ number_format((float) ($payment->order?->balance ?? 0), 2) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
