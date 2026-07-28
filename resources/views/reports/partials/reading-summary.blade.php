{{-- Shared KPI grid for X / Y / Z Reading screens. Expects $summary array. --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Gross Sales</div>
                <div class="fs-4 fw-bold">₱{{ number_format($summary['gross_sales'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Discounts</div>
                <div class="fs-4 fw-bold text-danger">₱{{ number_format($summary['total_discount'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">VAT Amount</div>
                <div class="fs-4 fw-bold">₱{{ number_format($summary['vat_amount'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Net Sales</div>
                <div class="fs-4 fw-bold text-success">₱{{ number_format($summary['net_sales'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">VATable Sales</div>
                <div class="fs-5 fw-bold">₱{{ number_format($summary['vatable_sales'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">VAT-Exempt Sales</div>
                <div class="fs-5 fw-bold">₱{{ number_format($summary['vat_exempt_sales'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Zero-Rated Sales</div>
                <div class="fs-5 fw-bold">₱{{ number_format($summary['zero_rated_sales'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Number of Transactions</div>
                <div class="fs-5 fw-bold">{{ number_format($summary['transaction_count']) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Customers Served</div>
                <div class="fs-5 fw-bold">{{ number_format($summary['customers_served']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Senior Citizen Discounts</div>
                <div class="fs-5 fw-bold">{{ number_format($summary['senior_count']) }} <span class="fs-6 text-muted">tx</span></div>
                <div class="text-muted small">₱{{ number_format($summary['senior_discount_amount'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">PWD Discounts</div>
                <div class="fs-5 fw-bold">{{ number_format($summary['pwd_count']) }} <span class="fs-6 text-muted">tx</span></div>
                <div class="text-muted small">₱{{ number_format($summary['pwd_discount_amount'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Other Discounts</div>
                <div class="fs-5 fw-bold">{{ number_format($summary['other_discount_count'] ?? 0) }} <span class="fs-6 text-muted">tx</span></div>
                <div class="text-muted small">₱{{ number_format($summary['other_discount_amount'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Refunds / Void Transactions</div>
                <div class="fs-5 fw-bold">{{ number_format($summary['voided_count']) }} <span class="fs-6 text-muted">tx</span></div>
                <div class="text-muted small">₱{{ number_format($summary['voided_amount'], 2) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold"><i data-lucide="credit-card" class="me-1"></i> Payment Method Breakdown</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Method</th><th class="text-end">Transactions</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse($summary['by_method'] as $row)
                            <tr>
                                <td>{{ ucfirst(is_array($row) ? $row['method'] : $row->method) }}</td>
                                <td class="text-end">{{ number_format(is_array($row) ? $row['transactions'] : $row->transactions) }}</td>
                                <td class="text-end">₱{{ number_format(is_array($row) ? $row['amount'] : $row->amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No payments in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold"><i data-lucide="building-2" class="me-1"></i> Sales by Branch</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Branch</th><th class="text-end">Transactions</th><th class="text-end">Collected</th></tr>
                        </thead>
                        <tbody>
                            @forelse($summary['sales_by_branch'] as $row)
                            <tr>
                                <td>{{ is_array($row) ? $row['branch_name'] : $row->branch_name }}</td>
                                <td class="text-end">{{ number_format(is_array($row) ? $row['transactions'] : $row->transactions) }}</td>
                                <td class="text-end">₱{{ number_format(is_array($row) ? $row['collected'] : $row->collected, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold"><i data-lucide="user" class="me-1"></i> Sales by Cashier</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Cashier</th><th class="text-end">Transactions</th><th class="text-end">Collected</th></tr>
                        </thead>
                        <tbody>
                            @forelse(($summary['sales_by_cashier'] ?? []) as $row)
                            <tr>
                                <td>{{ is_array($row) ? $row['cashier_name'] : $row->cashier_name }}</td>
                                <td class="text-end">{{ number_format(is_array($row) ? $row['transactions'] : $row->transactions) }}</td>
                                <td class="text-end">₱{{ number_format(is_array($row) ? $row['collected'] : $row->collected, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
