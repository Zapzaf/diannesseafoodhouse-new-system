@extends('layouts.app')

@section('page_title', 'Menu Order #' . $menuOrder->id . ' - Dianne\'s Seafood House')

@section('content')
<main>
    <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
        <div class="container-xl px-4">
            <div class="page-header-content pt-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto mt-4">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="shopping-bag"></i></div>
                            Menu Order #{{ $menuOrder->id }}
                        </h1>
                        <div class="page-header-subtitle">{{ $menuOrder->customerDisplayName() }} · {{ $menuOrder->branch->name ?? '—' }}</div>
                    </div>
                    <div class="col-auto mt-4 d-flex gap-2">
                        @if((string) $menuOrder->status === 'open')
                            @if((string) $menuOrder->payment_status !== 'paid' && (float) $menuOrder->balance > 0)
                            <a class="btn btn-success" href="{{ route('payments.create', ['menu_order_id' => $menuOrder->id]) }}">
                                <i class="me-1" data-feather="credit-card"></i> Pay
                            </a>
                            @endif
                            @if($menuOrder->payments->isEmpty())
                            <a class="btn btn-light text-primary" href="{{ route('menu-orders.edit', $menuOrder) }}">
                                <i class="me-1" data-feather="edit"></i> Edit
                            </a>
                            <form action="{{ route('menu-orders.destroy', $menuOrder) }}" method="POST" onsubmit="return confirm('Delete this menu order?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-light text-danger">
                                    <i class="me-1" data-feather="trash-2"></i> Delete
                                </button>
                            </form>
                            @endif
                            <form action="{{ route('menu-orders.complete', $menuOrder) }}" method="POST" onsubmit="return confirm('Mark this order as completed?')">
                                @csrf
                                <button type="submit" class="btn btn-light text-success">
                                    <i class="me-1" data-feather="check-circle"></i> Complete
                                </button>
                            </form>
                            @if($menuOrder->payments->isEmpty())
                            <form action="{{ route('menu-orders.cancel', $menuOrder) }}" method="POST" onsubmit="return confirm('Cancel this order?')">
                                @csrf
                                <button type="submit" class="btn btn-light text-warning">
                                    <i class="me-1" data-feather="x-circle"></i> Cancel
                                </button>
                            </form>
                            @endif
                        @endif
                        @if((string) $menuOrder->status === 'cancelled' && $menuOrder->payments->isEmpty())
                        <form action="{{ route('menu-orders.destroy', $menuOrder) }}" method="POST" onsubmit="return confirm('Delete this menu order?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-light text-danger">
                                <i class="me-1" data-feather="trash-2"></i> Delete
                            </button>
                        </form>
                        @endif
                        <a class="btn btn-light text-primary" href="{{ route('menu-orders.index') }}">
                            <i class="me-1" data-feather="arrow-left"></i> Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4 mt-n10">
        @include('layouts.alerts')

        <div class="row g-4">
            <div class="col-md-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">Order Profile</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Customer</dt><dd class="col-sm-7">{{ $menuOrder->customerDisplayName() }}</dd>
                            <dt class="col-sm-5">Branch</dt><dd class="col-sm-7">{{ $menuOrder->branch->name ?? '—' }}</dd>
                            <dt class="col-sm-5">Created</dt><dd class="col-sm-7">{{ $menuOrder->created_at?->format('M d, Y h:i A') }}</dd>
                            <dt class="col-sm-5">Order Status</dt><dd class="col-sm-7"><span class="badge-status badge-{{ strtolower((string) $menuOrder->status) }}">{{ ucfirst((string) $menuOrder->status) }}</span></dd>
                            <dt class="col-sm-5">Payment Status</dt><dd class="col-sm-7"><span class="badge-status badge-{{ strtolower((string) $menuOrder->payment_status) }}">{{ ucfirst((string) $menuOrder->payment_status) }}</span></dd>
                            @php $rPax = (int)($menuOrder->regular_pax ?? 0); $pPax = (int)($menuOrder->pwd_pax ?? 0); $sPax = (int)($menuOrder->senior_pax ?? 0); @endphp
                            @if($rPax + $pPax + $sPax > 0)
                            <dt class="col-sm-5 mt-2">Customers</dt>
                            <dd class="col-sm-7 mt-2">
                                @if($rPax > 0)<span class="badge bg-secondary me-1">{{ $rPax }} Regular</span>@endif
                                @if($pPax > 0)<span class="badge bg-primary me-1">{{ $pPax }} PWD</span>@endif
                                @if($sPax > 0)<span class="badge bg-success">{{ $sPax }} Senior</span>@endif
                            </dd>
                            @php
                                $discountIds   = json_decode($menuOrder->discount_id_number ?? '', true) ?? [];
                                $discountNames = json_decode($menuOrder->discount_name       ?? '', true) ?? [];
                                $hasPwdDetails    = !empty($discountIds['pwd'])    || !empty($discountNames['pwd']);
                                $hasSeniorDetails = !empty($discountIds['senior']) || !empty($discountNames['senior']);
                            @endphp
                            @if($hasPwdDetails || $hasSeniorDetails)
                            <dt class="col-sm-5 mt-2">Discount IDs</dt>
                            <dd class="col-sm-7 mt-2">
                                @foreach(['pwd' => 'PWD', 'senior' => 'Senior'] as $type => $label)
                                    @php $ids = $discountIds[$type] ?? []; $names = $discountNames[$type] ?? []; $count = max(count($ids), count($names)); @endphp
                                    @for($i = 0; $i < $count; $i++)
                                    <div class="mb-1">
                                        <span class="badge {{ $type === 'pwd' ? 'bg-primary' : 'bg-success' }} me-1">{{ $label }} #{{ $i+1 }}</span>
                                        <span class="fw-semibold small">{{ $ids[$i] ?? '—' }}</span>
                                        @if(!empty($names[$i]))<span class="text-muted small ms-1">({{ $names[$i] }})</span>@endif
                                    </div>
                                    @endfor
                                @endforeach
                            </dd>
                            @endif
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">Billing Summary</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td>Menu Subtotal</td><td class="text-end">₱{{ number_format((float) $menuOrder->subtotal, 2) }}</td></tr>
                                @if((float) ($menuOrder->additional_charge_amount ?? 0) > 0)
                                <tr><td>Additional Charges</td><td class="text-end">₱{{ number_format((float) $menuOrder->additional_charge_amount, 2) }}</td></tr>
                                @if($menuOrder->additional_charge_label)
                                <tr class="text-muted small"><td class="ps-4">- {{ $menuOrder->additional_charge_label }}</td><td></td></tr>
                                @endif
                                @endif
                                <tr><td>Discount</td><td class="text-end text-danger">- ₱{{ number_format((float) $menuOrder->discount_amount, 2) }}</td></tr>
                                <tr><td>VAT ({{ number_format((float) $menuOrder->vat_rate, 2) }}%)</td><td class="text-end">₱{{ number_format((float) $menuOrder->vat_amount, 2) }}</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end">₱{{ number_format((float) $menuOrder->total_amount, 2) }}</td></tr>
                                <tr class="text-success"><td>Amount Paid</td><td class="text-end">₱{{ number_format((float) $menuOrder->amount_paid, 2) }}</td></tr>
                                <tr class="{{ (float) $menuOrder->balance > 0 ? 'text-danger fw-bold' : 'text-success' }}"><td>Balance</td><td class="text-end">₱{{ number_format((float) $menuOrder->balance, 2) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @if((string) $menuOrder->payment_status !== 'paid')
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Record Payment</div>
                    <div class="card-body">
                        <form action="{{ route('menu-orders.payments.store', $menuOrder) }}" method="POST">
                            @csrf
                            @php $balance = number_format((float) $menuOrder->balance, 2, '.', ''); @endphp
                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount Tendered <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">PHP</span>
                                    <input type="number" name="amount_tendered" id="amountTendered" class="form-control @error('amount_tendered') is-invalid @enderror"
                                           value="{{ old('amount_tendered', $balance) }}" min="0.01" step="0.01" required
                                           data-balance="{{ $balance }}">
                                </div>
                                @error('amount_tendered')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <input type="hidden" name="amount" id="amountApplied" value="{{ $balance }}">
                            <div class="mb-3 p-3 rounded" id="changeDisplay" style="background:#f0fdf4;border:1px solid #bbf7d0;display:none;">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold text-muted">Balance Due:</span>
                                    <span class="fw-semibold">₱{{ number_format((float)$menuOrder->balance, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="fw-bold text-success">Change:</span>
                                    <span class="fw-bold text-success" id="changeAmt">₱0.00</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Method <span class="text-danger">*</span></label>
                                <select name="method" class="form-select @error('method') is-invalid @enderror" required>
                                    @foreach(['cash' => 'Cash', 'gcash' => 'GCash', 'card' => 'Card', 'bank' => 'Bank Transfer'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('method', 'cash') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" value="{{ old('reference_number') }}" maxlength="100">
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="me-1" data-feather="save"></i> Save Payment</button>
                        </form>
                        <script>
                        (function(){
                            var inp = document.getElementById('amountTendered');
                            var hiddenAmt = document.getElementById('amountApplied');
                            var changeDisplay = document.getElementById('changeDisplay');
                            var changeAmt = document.getElementById('changeAmt');
                            var balance = parseFloat(inp.dataset.balance || 0);
                            function update() {
                                var tendered = parseFloat(inp.value || 0);
                                var applied = Math.min(tendered, balance);
                                var change = Math.max(0, tendered - balance);
                                hiddenAmt.value = applied.toFixed(2);
                                if (tendered > balance) {
                                    changeDisplay.style.display = '';
                                    changeAmt.textContent = '₱' + change.toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2});
                                } else {
                                    changeDisplay.style.display = 'none';
                                }
                            }
                            inp.addEventListener('input', update);
                            update();
                        })();
                        </script>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">Ordered Items</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Menu Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-end">COGS</th>
                                        <th class="text-end">Profit</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menuOrder->items as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->menu->name ?? 'N/A' }}</td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end">₱{{ number_format((float) $item->subtotal, 2) }}</td>
                                        <td class="text-end">₱{{ number_format((float) $item->cost, 2) }}</td>
                                        <td class="text-end {{ (float) $item->profit >= 0 ? 'text-success' : 'text-danger' }}">₱{{ number_format((float) $item->profit, 2) }}</td>
                                        <td>
                                            @if($item->inventory_deducted)
                                                <span class="badge bg-success" title="All ingredients deducted from stock">
                                                    <i data-feather="check" style="width:11px;height:11px;"></i> Deducted
                                                </span>
                                            @elseif($item->menu && $item->menu->items->isEmpty())
                                                <span class="badge bg-warning text-dark" title="No ingredients configured for this menu item">
                                                    No Recipe
                                                </span>
                                            @else
                                                <span class="badge bg-secondary" title="Inventory was not fully deducted (possible insufficient stock)">
                                                    Pending
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">No items on this order.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Payment History</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Amount Applied</th>
                                        <th class="text-end">Tendered</th>
                                        <th class="text-end">Change</th>
                                        <th>Reference</th>
                                        <th>OR No.</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menuOrder->payments->sortByDesc('created_at') as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_date?->format('M d, Y') }}</td>
                                        <td><span class="badge bg-secondary">{{ ucfirst((string) $payment->method) }}</span></td>
                                        <td class="text-end text-success fw-semibold">₱{{ number_format((float) $payment->amount, 2) }}</td>
                                        <td class="text-end">₱{{ number_format((float) $payment->amount_tendered, 2) }}</td>
                                        <td class="text-end text-danger">₱{{ number_format((float) $payment->change_amount, 2) }}</td>
                                        <td>{{ $payment->reference_number ?: '—' }}</td>
                                        <td class="fw-semibold">{{ $payment->or_number ?: '—' }}</td>
                                        <td><a href="{{ route('menu-orders.payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i data-feather="printer" style="width:14px;height:14px;"></i></a></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="8" class="text-center text-muted py-3">No payments yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection