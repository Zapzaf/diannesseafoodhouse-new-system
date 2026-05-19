# Laravel Expense Module — Agent Prompt v3
# Payments as Sales + Branch Support + Correct Layout + Read-Only

You are an expert Laravel developer. Build a complete expense tracking module
for an existing Laravel web app. Follow these exact specifications carefully.

---

## CONTEXT — EXISTING APP PATTERNS

### Layout rules (MUST follow exactly)
Study resources/views/inventory/transactions.blade.php as the reference.
All expense views MUST match this layout pattern:

1. Extend layout:
   @extends('layouts.app')
   @section('page_title', '...')
   @section('content')
   <main>

2. Page header — use the x-page-header component (NOT raw <header> HTML):
   <x-page-header title="..." subtitle="..." icon="...">
       {{-- optional right-side buttons slot --}}
   </x-page-header>

3. Main content wrapper:
   <div class="container-xl px-4 mt-n10">
       @include('layouts.alerts')
       ...
   </div>

4. Cards:
   <div class="card shadow-sm">
       <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
           ...
       </div>
       <div class="card-body">...</div>
       <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
           ...
       </div>
   </div>

5. Tables:
   <div class="table-responsive">
       <table class="table table-striped table-bordered">
           <thead class="table-dark">...</thead>
           <tbody>
               @forelse(...) ... @empty
               <tr><td colspan="..." class="text-center text-muted py-4">No records found.</td></tr>
               @endforelse
           </tbody>
       </table>
   </div>

6. Pagination (in card-footer):
   <div class="text-muted small">
       Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }}
       of {{ $items->total() }} entries
   </div>
   <div class="mb-0 custom-pagination-wrapper">
       {{ $items->onEachSide(1)->links('pagination::bootstrap-5') }}
   </div>
   Include this style block (copy exactly):
   <style>
       .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
       .custom-pagination-wrapper p.small.text-muted { display: none !important; }
       .custom-pagination-wrapper .pagination { margin-bottom: 0 !important; }
   </style>

7. Icons: Feather icons via data-feather attribute
   e.g. <i data-feather="trending-down" class="me-1"></i>

8. Admin branch column pattern (copy this exactly):
   @if(auth()->user()?->isAdmin())
   <th>Branch</th>
   @endif
   ...
   @if(auth()->user()?->isAdmin())
   <td>{{ $record->branch?->name ?? '—' }}</td>
   @endif

9. Currency format: ₱{{ number_format((float) $value, 2) }}

10. Per-page + search filter form (in card-header, copy from inventory pattern):
    <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
        <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <option value="10" ...>10</option>
            <option value="20" ...>20</option>
            <option value="50" ...>50</option>
        </select>
        <div class="input-group input-group-sm" style="max-width: 250px;">
            <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">
                <i data-feather="search" style="width:14px;height:14px;"></i>
            </button>
        </div>
    </form>

### Existing Payments Module (DO NOT MODIFY)
Table: menu_order_payments
Key columns:
  - id, branch_id, menu_order_id, payment_date (date)
  - amount, method (cash/gcash/card/bank)
  - discount_amount, vat_amount, total_vat_exempt
  - final_total, or_number, reference_number, received_by

### Auth & Branch Scoping (copy this pattern exactly):
  $user = auth()->user();
  if (!$user->isAdmin()) {
      $query->where('branch_id', $user->branch_id);
  } elseif (session('selected_branch_id')) {
      $query->where('branch_id', session('selected_branch_id'));
  }

---

## IMPORTANT — USER PERMISSIONS

Users CANNOT create, edit, or delete expense records manually.
The ONLY way data enters the system is via .xlsx file import.
There are NO create/edit/delete forms, routes, or buttons anywhere.
The module is READ + IMPORT + EXPORT only.

---

## STEP 1 — INSTALL

Run:
  composer require maatwebsite/excel
  php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"

---

## STEP 2 — DATABASE

DO NOT create a daily_sales table.
Sales data comes from existing menu_order_payments table.

Create migrations for 3 new tables only:

### vatable_purchases
  - id
  - branch_id (unsignedBigInteger, nullable, FK to branches)
  - date (date, nullable)
  - vendor_name (string)
  - address (string, nullable)
  - si_number (string, nullable)
  - tin (string, nullable)
  - gross_amount (decimal 12,2, default 0)
  - vat (decimal 12,2, default 0)
  - net_purchases (decimal 12,2, default 0)
  - month_year (string)
  - timestamps

### non_vatable_purchases
  - id
  - branch_id (unsignedBigInteger, nullable, FK to branches)
  - date (date, nullable)
  - vendor_name (string)
  - gross_amount (decimal 12,2, default 0)
  - month_year (string)
  - timestamps

### cash_disbursements
  - id
  - branch_id (unsignedBigInteger, nullable, FK to branches)
  - date (date, nullable)
  - check_number (string, nullable)
  - payee (string)
  - amount (decimal 12,2, default 0)
  - reference (string, nullable)
  - month_year (string)
  - timestamps

Run: php artisan migrate

---

## STEP 3 — MODELS

Create 3 Eloquent models: VatablePurchase, NonVatablePurchase, CashDisbursement
Each model:
  - $fillable covers all columns including branch_id and month_year
  - branch() belongsTo Branch

---

## STEP 4 — IMPORTS

Create app/Imports/ExpenseImport.php implementing WithMultipleSheets.
Constructor: __construct(public string $monthYear, public ?int $branchId)

Sheet index mapping (3 sheets, no daily sales):
  0 => VatablePurchasesSheet
  1 => NonVatableSheet
  2 => CashDisbursementSheet

### VatablePurchasesSheet
  Implements: ToModel, WithStartRow, WithChunkReading
  StartRow: 5
  Chunk size: 200
  Constructor receives: $monthYear, $branchId
  Column map:
    col0 = date
    col1 = vendor_name
    col2 = address
    col3 = si_number
    col4 = tin
    col5 = gross_amount
    col6 = vat
    col7 = net_purchases
  Skip if vendor_name is empty or null
  Set branch_id and month_year from constructor

### NonVatableSheet
  Implements: ToModel, WithStartRow, WithChunkReading
  StartRow: 5
  Chunk size: 200
  Constructor receives: $monthYear, $branchId
  Column map:
    col0 = date
    col1 = vendor_name
    col5 = gross_amount
  Skip if vendor_name is empty or null
  Set branch_id and month_year from constructor

### CashDisbursementSheet
  Implements: ToModel, WithStartRow, WithChunkReading
  StartRow: 5
  Chunk size: 200
  Constructor receives: $monthYear, $branchId
  Column map:
    col0 = date
    col1 = check_number
    col2 = payee
    col3 = amount
    col4 = reference
  Skip if payee is empty or null
  Set branch_id and month_year from constructor

Date handling in ALL sheets:
  - If numeric: \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
  - If string: Carbon::parse($value)
  - If null: store null

---

## STEP 5 — EXPORTS

Create app/Exports/ExpenseReportExport.php implementing WithMultipleSheets.
Constructor: __construct(public string $monthYear, public ?int $branchId)
Returns 4 sheet exports.

Each export sheet implements:
  FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize

### PaymentsSalesExport
  Title: "DAILY SALES"
  Query MenuOrderPayment grouped by payment_date:
    SELECT
      payment_date,
      branch_id,
      SUM(CASE WHEN method='cash' THEN amount ELSE 0 END) as cash,
      SUM(CASE WHEN method='gcash' THEN amount ELSE 0 END) as gcash,
      SUM(CASE WHEN method='card' THEN amount ELSE 0 END) as card,
      SUM(CASE WHEN method='bank' THEN amount ELSE 0 END) as bank,
      SUM(final_total) as gross_sales,
      SUM(vat_amount) as vat_amount,
      SUM(total_vat_exempt) as vat_exempt,
      SUM(discount_amount) as discount,
      SUM(amount) as net_sales
    WHERE payment_date LIKE '{monthYear}%'
    AND (branch_id = $branchId or $branchId is null)
    GROUP BY payment_date, branch_id
    ORDER BY payment_date ASC
  Headings:
    Date, Branch, Cash, GCash, Card, Bank, Gross Sales,
    VAT Amount, VAT Exempt, Discount, Net Sales
  Add totals row for all numeric columns at bottom.

### VatablePurchasesExport
  Title: "VATABLE"
  Query: VatablePurchase scoped by month_year + branch_id
  Headings: Date, Branch, Vendor Name, Address, SI No., TIN,
            Gross Amount, VAT, Net Purchases

### NonVatableExport
  Title: "NON-VATABLE"
  Query: NonVatablePurchase scoped by month_year + branch_id
  Headings: Date, Branch, Vendor Name, Gross Amount

### CashDisbursementExport
  Title: "CASH DISBURSEMENT"
  Query: CashDisbursement scoped by month_year + branch_id
  Headings: Date, Branch, Check No., Payee, Amount, Reference
  Add totals row for Amount column at bottom.

Styling for all sheets:
  - Row 1: bold, background color #2D3748 (dark), font color white
  - Freeze row 1
  - Number format for all decimal columns: #,##0.00
  - Totals row: bold, background color #EDF2F7

---

## STEP 6 — CONTROLLER

Create app/Http/Controllers/ExpenseController.php

Private branch scope helper (copy exactly):
  private function applyBranchScope($query)
  {
      $user = auth()->user();
      if (!$user->isAdmin()) {
          $query->where('branch_id', $user->branch_id);
      } elseif (session('selected_branch_id')) {
          $query->where('branch_id', session('selected_branch_id'));
      }
      return $query;
  }

Private branchId helper:
  private function currentBranchId(): ?int
  {
      $user = auth()->user();
      if (!$user->isAdmin()) return (int) $user->branch_id;
      return session('selected_branch_id') ? (int) session('selected_branch_id') : null;
  }

### index()
  - Get distinct month_years from menu_order_payments (payment_date as YYYY-MM)
    scoped by branch, ordered desc
  - For each month_year compute:
      gross_sales = SUM(final_total)
      net_sales = SUM(amount)
      total_vatable = SUM from vatable_purchases
      total_non_vatable = SUM from non_vatable_purchases
      total_disbursements = SUM from cash_disbursements
  - Pass: $months (collection), $branches (all branches for admin),
    $selectedBranchId = currentBranchId()
  - Return view('expenses.index', ...)

### import(Request $request)
  - Validate:
      month_year: required|string|regex:/^\d{4}-\d{2}$/
      file: required|mimes:xlsx,xls|max:10240
      branch_id: nullable|exists:branches,id
  - Determine branchId:
      Non-admin: force auth()->user()->branch_id
      Admin: use $request->branch_id or null (all branches)
  - Wrap in DB::transaction():
      Delete existing records for month_year + branch_id:
        VatablePurchase, NonVatablePurchase, CashDisbursement
        (scope delete by branch_id only if not null)
      Excel::import(new ExpenseImport($monthYear, $branchId), $request->file('file'))
  - On exception: DB::rollBack(), return back()->with('error', $e->getMessage())
  - On success: return back()->with('success', 'Expenses imported successfully.')

### export(Request $request, string $monthYear)
  - Determine branchId via currentBranchId() or $request->branch_id for admin
  - Get branch name for filename if scoped
  - $filename = 'expense_' . $monthYear
              . ($branchId ? '_' . Str::slug($branchName) : '')
              . '.xlsx'
  - Return Excel::download(new ExpenseReportExport($monthYear, $branchId), $filename)

### show(Request $request, string $monthYear)
  - Determine branchId via currentBranchId()
  - Build summary:
      $salesQuery = MenuOrderPayment::query()
          ->where('payment_date', 'like', $monthYear . '%')
      Apply applyBranchScope($salesQuery)
      gross_sales = clone->sum('final_total')
      net_sales = clone->sum('amount')
      vat_total = clone->sum('vat_amount')
      discount_total = clone->sum('discount_amount')
      sales_by_method = clone->selectRaw('method, SUM(amount) as total')
                             ->groupBy('method')->pluck('total','method')

      total_vatable = VatablePurchase scoped->where('month_year', $monthYear)->sum('gross_amount')
      total_non_vatable = NonVatablePurchase scoped->where('month_year', $monthYear)->sum('gross_amount')
      total_disbursements = CashDisbursement scoped->where('month_year', $monthYear)->sum('amount')

  - Paginated datasets (15/page, use ->appends(request()->query())):
      $salesRecords = MenuOrderPayment with order.branch, receivedBy
                      scoped + month_year filter + search + paginate(15)
      $vatableRecords = VatablePurchase scoped + month_year + search + paginate(15)
      $nonVatableRecords = NonVatablePurchase scoped + month_year + search + paginate(15)
      $disbursementRecords = CashDisbursement scoped + month_year + search + paginate(15)

  - Search applies to: vendor_name/payee/or_number depending on tab
    Use request('search') and request('tab') to know which dataset to search

  - Pass all to: return view('expenses.show', compact(...))

---

## STEP 7 — ROUTES

In routes/web.php, add inside auth middleware group:
  Route::prefix('expenses')->name('expenses.')->group(function () {
      Route::get('/', [ExpenseController::class, 'index'])->name('index');
      Route::post('/import', [ExpenseController::class, 'import'])->name('import');
      Route::get('/export/{monthYear}', [ExpenseController::class, 'export'])->name('export');
      Route::get('/{monthYear}', [ExpenseController::class, 'show'])->name('show');
  });

---

## STEP 8 — VIEWS

### resources/views/expenses/index.blade.php

@extends('layouts.app')
@section('page_title', 'Expenses')
@section('content')
<main>

<x-page-header title="Expenses" subtitle="Monthly expense and sales summary by branch" icon="trending-down">
    {{-- Import button triggers modal --}}
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
        <i data-feather="upload" class="me-1"></i> Import
    </button>
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    {{-- Admin branch filter --}}
    @if(auth()->user()?->isAdmin())
    <div class="mb-3 d-flex align-items-center gap-2">
        <label class="fw-semibold small mb-0">Branch:</label>
        <form method="GET" action="{{ route('expenses.index') }}" class="d-flex gap-2">
            <select name="branch_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
                @endforeach
            </select>
        </form>
    </div>
    @endif

    {{-- Months summary table --}}
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <i data-feather="calendar" class="me-1" style="width:16px;height:16px;"></i> Imported Months
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th class="text-end">Gross Sales</th>
                            <th class="text-end">Net Sales</th>
                            <th class="text-end">Vatable Purchases</th>
                            <th class="text-end">Non-Vatable</th>
                            <th class="text-end">Disbursements</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($months as $month)
                        <tr>
                            <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $month->month_year)->format('F Y') }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $month->branch_name ?? 'All' }}</td>
                            @endif
                            <td class="text-end">₱{{ number_format((float) $month->gross_sales, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->net_sales, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->total_vatable, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->total_non_vatable, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $month->total_disbursements, 2) }}</td>
                            <td class="text-center">
                                <a href="{{ route('expenses.show', $month->month_year) }}" class="btn btn-sm btn-outline-primary">
                                    <i data-feather="eye" style="width:14px;height:14px;"></i> View
                                </a>
                                <a href="{{ route('expenses.export', $month->month_year) }}{{ $selectedBranchId ? '?branch_id='.$selectedBranchId : '' }}"
                                   class="btn btn-sm btn-outline-success">
                                    <i data-feather="download" style="width:14px;height:14px;"></i> Export
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->isAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">
                                No expense data imported yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('expenses.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="upload" class="me-1"></i> Import Expense File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                        <input type="month" name="month_year" class="form-control" required>
                        <div class="form-text">Select the month this file covers.</div>
                    </div>
                    @if(auth()->user()?->isAdmin())
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">-- Select Branch --</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-semibold">File (.xlsx / .xls) <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        <div class="form-text">
                            File must have 3 sheets in order: Vatable Purchases, Non-Vatable, Cash Disbursement.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="upload" class="me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</main>
@endsection

---

### resources/views/expenses/show.blade.php

@extends('layouts.app')
@section('page_title', 'Expenses — ' . $monthLabel)
@section('content')
<main>

<x-page-header title="{{ $monthLabel }}" subtitle="Expense and sales breakdown" icon="file-text">
    <a href="{{ route('expenses.export', $monthYear) }}{{ $selectedBranchId ? '?branch_id='.$selectedBranchId : '' }}"
       class="btn btn-success">
        <i data-feather="download" class="me-1"></i> Export Excel
    </a>
    <a href="{{ route('expenses.index') }}" class="btn btn-light text-primary ms-2">
        <i data-feather="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4 mt-n10">
    @include('layouts.alerts')

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-primary border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Gross Sales</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) $grossSales, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-success border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Net Sales</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) $netSales, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-warning border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Total Purchases</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) ($totalVatable + $totalNonVatable), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-start border-danger border-4">
                <div class="card-body py-3">
                    <div class="small text-muted fw-semibold">Disbursements</div>
                    <div class="fw-bold fs-5">₱{{ number_format((float) $totalDisbursements, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-0" id="expenseTabs">
        <li class="nav-item">
            <a class="nav-link {{ request('tab','sales') === 'sales' ? 'active' : '' }}"
               href="?tab=sales">
               <i data-feather="trending-up" style="width:14px;height:14px;" class="me-1"></i> Daily Sales
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('tab') === 'vatable' ? 'active' : '' }}"
               href="?tab=vatable">
               <i data-feather="file-minus" style="width:14px;height:14px;" class="me-1"></i> Vatable
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('tab') === 'nonvatable' ? 'active' : '' }}"
               href="?tab=nonvatable">
               <i data-feather="file" style="width:14px;height:14px;" class="me-1"></i> Non-Vatable
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('tab') === 'disbursements' ? 'active' : '' }}"
               href="?tab=disbursements">
               <i data-feather="credit-card" style="width:14px;height:14px;" class="me-1"></i> Disbursements
            </a>
        </li>
    </ul>

    {{-- Tab content (each in its own card matching inventory pattern) --}}
    {{-- Render only the active tab's card to avoid pagination conflicts --}}

    @php $activeTab = request('tab', 'sales'); @endphp

    <div class="card shadow-sm" style="border-top-left-radius:0;">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                @if($activeTab === 'sales') <i data-feather="trending-up" class="me-1" style="width:16px;height:16px;"></i> Daily Sales (from Payments)
                @elseif($activeTab === 'vatable') <i data-feather="file-minus" class="me-1" style="width:16px;height:16px;"></i> Vatable Purchases
                @elseif($activeTab === 'nonvatable') <i data-feather="file" class="me-1" style="width:16px;height:16px;"></i> Non-Vatable Purchases
                @else <i data-feather="credit-card" class="me-1" style="width:16px;height:16px;"></i> Cash Disbursements
                @endif
            </div>
            {{-- Search form --}}
            <form method="GET" action="{{ url()->current() }}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="input-group input-group-sm" style="max-width:250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i data-feather="search" style="width:14px;height:14px;"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                @if($activeTab === 'sales')
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>OR No.</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Final Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesRecords as $record)
                        <tr>
                            <td>{{ $salesRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->payment_date?->format('M d, Y') }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $record->order?->branch?->name ?? '—' }}</td>
                            @endif
                            <td class="text-muted small">{{ $record->or_number ?? '—' }}</td>
                            <td>{{ $record->order?->customerDisplayName() ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($record->method) }}</span></td>
                            <td class="text-end">₱{{ number_format((float) $record->amount, 2) }}</td>
                            <td class="text-end text-muted">₱{{ number_format((float) $record->discount_amount, 2) }}</td>
                            <td class="text-end text-muted">₱{{ number_format((float) $record->vat_amount, 2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float) $record->final_total, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 10 : 9 }}" class="text-center text-muted py-4">No sales records for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @elseif($activeTab === 'vatable')
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>Vendor Name</th>
                            <th>Address</th>
                            <th>SI No.</th>
                            <th>TIN</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vatableRecords as $record)
                        <tr>
                            <td>{{ $vatableRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->date?->format('M d, Y') ?? '—' }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $record->branch?->name ?? '—' }}</td>
                            @endif
                            <td>{{ $record->vendor_name }}</td>
                            <td class="text-muted small">{{ $record->address ?? '—' }}</td>
                            <td class="text-muted small">{{ $record->si_number ?? '—' }}</td>
                            <td class="text-muted small">{{ $record->tin ?? '—' }}</td>
                            <td class="text-end">₱{{ number_format((float) $record->gross_amount, 2) }}</td>
                            <td class="text-end">₱{{ number_format((float) $record->vat, 2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float) $record->net_purchases, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 10 : 9 }}" class="text-center text-muted py-4">No vatable purchases for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @elseif($activeTab === 'nonvatable')
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>Vendor Name</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($nonVatableRecords as $record)
                        <tr>
                            <td>{{ $nonVatableRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->date?->format('M d, Y') ?? '—' }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $record->branch?->name ?? '—' }}</td>
                            @endif
                            <td>{{ $record->vendor_name }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float) $record->gross_amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 5 : 4 }}" class="text-center text-muted py-4">No non-vatable purchases for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @else
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            @if(auth()->user()?->isAdmin())<th>Branch</th>@endif
                            <th>Check No.</th>
                            <th>Payee</th>
                            <th class="text-end">Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($disbursementRecords as $record)
                        <tr>
                            <td>{{ $disbursementRecords->firstItem() + $loop->index }}</td>
                            <td class="text-muted small">{{ $record->date?->format('M d, Y') ?? '—' }}</td>
                            @if(auth()->user()?->isAdmin())
                            <td class="text-muted small">{{ $record->branch?->name ?? '—' }}</td>
                            @endif
                            <td class="text-muted small">{{ $record->check_number ?? '—' }}</td>
                            <td>{{ $record->payee }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format((float) $record->amount, 2) }}</td>
                            <td class="text-muted small">{{ $record->reference ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 7 : 6 }}" class="text-center text-muted py-4">No disbursements for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            @php
                $activePaginator = match($activeTab) {
                    'vatable' => $vatableRecords,
                    'nonvatable' => $nonVatableRecords,
                    'disbursements' => $disbursementRecords,
                    default => $salesRecords,
                };
            @endphp
            <div class="text-muted small">
                Showing {{ $activePaginator->firstItem() ?? 0 }}
                to {{ $activePaginator->lastItem() ?? 0 }}
                of {{ $activePaginator->total() }} entries
            </div>
            <div class="mb-0 custom-pagination-wrapper">
                {{ $activePaginator->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
        <style>
            .custom-pagination-wrapper nav { margin-bottom: 0 !important; }
            .custom-pagination-wrapper p.small.text-muted { display: none !important; }
            .custom-pagination-wrapper .pagination { margin-bottom: 0 !important; }
        </style>
    </div>
</div>
</main>
@endsection

---

## STEP 9 — SIDEBAR NAVIGATION

Open resources/views/layouts/app.blade.php.
Study the existing nav group pattern carefully.

The app uses Feather icons via data-feather attribute (confirmed).
Match EXACTLY the existing markup, class names, and indentation.

Find the most relevant group (Finance, Accounting, Reports).
If none fits, create a new "Finance" group using the same pattern.

Insert nav item:
  - Icon: data-feather="trending-down"
  - Label: Expenses
  - Route: {{ route('expenses.index') }}
  - Active: {{ request()->routeIs('expenses.*') ? 'active' : '' }}
  - Add wire:navigate if other links use it
  - Add tooltip/badge pattern if other links use it (leave empty)

Output only the modified nav group block after editing.

---

## STEP 10 — EDGE CASES & VALIDATION

  - Users cannot manually create, edit, or delete any expense records
  - Skip blank rows silently during import
  - Handle both Excel serial dates and string dates
  - DB::transaction() wraps entire import; roll back on any error
  - Non-admin branch_id is always forced from auth()->user()->branch_id
  - Paginated tabs preserve active tab via ?tab= query param
  - All ->appends(request()->query()) on paginators to preserve filters
  - Empty state rows use correct dynamic colspan accounting for admin branch column
  - Currency always formatted as ₱ with number_format((float) $value, 2)

---

## FINAL OUTPUT

After generating all files output:
  1. Complete list of every file created or modified with full path
  2. Exact commands to run in order (composer, artisan)
  3. Modified sidebar nav group block from app.blade.php for review
  4. Confirmation that menu_order_payments was NOT modified