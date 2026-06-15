<?php

namespace App\Http\Controllers;

use App\Exports\ExpenseReportExport;
use App\Imports\ExpenseImport;
use App\Models\Branch;
use App\Models\CashDisbursement;
use App\Models\MenuOrderPayment;
use App\Models\NonVatablePurchase;
use App\Models\VatablePurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseController extends Controller
{
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

    private function currentBranchId(): ?int
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return (int) $user->branch_id;
        }
        return session('selected_branch_id') ? (int) session('selected_branch_id') : null;
    }

    public function index()
    {
        $selectedBranchId = $this->currentBranchId();
        
        $monthsQuery = MenuOrderPayment::query()
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month_year")
            ->whereNotNull('payment_date')
            ->groupBy('month_year')
            ->orderBy('month_year', 'desc');
            
        $monthsQuery = $this->applyBranchScope($monthsQuery);
        $monthYears = $monthsQuery->pluck('month_year');
        
        $months = collect();
        foreach ($monthYears as $my) {
            $salesQuery = MenuOrderPayment::where('payment_date', 'like', $my . '%');
            $salesQuery = $this->applyBranchScope($salesQuery);
            $gross_sales = (float) $salesQuery->sum('final_total');
            $net_sales = (float) $salesQuery->sum('amount');
            
            $vatableQuery = VatablePurchase::where('month_year', $my);
            $vatableQuery = $this->applyBranchScope($vatableQuery);
            $total_vatable = (float) $vatableQuery->sum('gross_amount');
            
            $nonVatableQuery = NonVatablePurchase::where('month_year', $my);
            $nonVatableQuery = $this->applyBranchScope($nonVatableQuery);
            $total_non_vatable = (float) $nonVatableQuery->sum('gross_amount');
            
            $disbursementsQuery = CashDisbursement::where('month_year', $my);
            $disbursementsQuery = $this->applyBranchScope($disbursementsQuery);
            $total_disbursements = (float) $disbursementsQuery->sum('amount');
            
            $branchName = null;
            if ($selectedBranchId) {
                $branchName = Branch::find($selectedBranchId)?->name;
            }
            
            $months->push((object) [
                'month_year' => $my,
                'branch_name' => $branchName,
                'gross_sales' => $gross_sales,
                'net_sales' => $net_sales,
                'total_vatable' => $total_vatable,
                'total_non_vatable' => $total_non_vatable,
                'total_disbursements' => $total_disbursements,
            ]);
        }

        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        return view('expenses.index', compact('months', 'branches', 'selectedBranchId'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'month_year' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'file' => 'required|mimes:xlsx,xls|max:10240',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $user = auth()->user();
        $branchId = null;
        
        if (!$user->isAdmin()) {
            $branchId = (int) $user->branch_id;
        } else {
            $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;
        }
        
        $monthYear = $request->input('month_year');

        try {
            DB::transaction(function () use ($monthYear, $branchId, $request) {
                $vatableDelete = VatablePurchase::where('month_year', $monthYear);
                $nonVatableDelete = NonVatablePurchase::where('month_year', $monthYear);
                $disbursementsDelete = CashDisbursement::where('month_year', $monthYear);
                
                if ($branchId !== null) {
                    $vatableDelete->where('branch_id', $branchId);
                    $nonVatableDelete->where('branch_id', $branchId);
                    $disbursementsDelete->where('branch_id', $branchId);
                }
                
                $vatableDelete->delete();
                $nonVatableDelete->delete();
                $disbursementsDelete->delete();

                Excel::import(new ExpenseImport($monthYear, $branchId), $request->file('file'));
            });
            
            return back()->with('success', 'Expenses imported successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function export(Request $request, string $monthYear)
    {
        $branchId = $this->currentBranchId();
        if (auth()->user()->isAdmin() && $request->has('branch_id')) {
            $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;
        }
        
        $branchName = '';
        if ($branchId) {
            $branchName = Branch::find($branchId)?->name ?? '';
        }

        $filename = 'expense_' . $monthYear 
                  . ($branchId ? '_' . Str::slug($branchName) : '') 
                  . '.xlsx';

        return Excel::download(new ExpenseReportExport($monthYear, $branchId), $filename);
    }

    public function show(Request $request, string $monthYear)
    {
        $selectedBranchId = $this->currentBranchId();
        
        $salesQuery = MenuOrderPayment::query()
            ->where('payment_date', 'like', $monthYear . '%');
        $salesQuery = $this->applyBranchScope($salesQuery);
        
        $grossSales = (clone $salesQuery)->sum('final_total');
        $netSales = (clone $salesQuery)->sum('amount');
        $vatTotal = (clone $salesQuery)->sum('vat_amount');
        $discountTotal = (clone $salesQuery)->sum('discount_amount');
        $salesByMethod = (clone $salesQuery)->selectRaw('method, SUM(amount) as total')
                                            ->groupBy('method')->pluck('total', 'method');

        $vatableQuery = VatablePurchase::where('month_year', $monthYear);
        $vatableQuery = $this->applyBranchScope($vatableQuery);
        $totalVatable = (float) $vatableQuery->sum('gross_amount');

        $nonVatableQuery = NonVatablePurchase::where('month_year', $monthYear);
        $nonVatableQuery = $this->applyBranchScope($nonVatableQuery);
        $totalNonVatable = (float) $nonVatableQuery->sum('gross_amount');

        $disbursementsQuery = CashDisbursement::where('month_year', $monthYear);
        $disbursementsQuery = $this->applyBranchScope($disbursementsQuery);
        $totalDisbursements = (float) $disbursementsQuery->sum('amount');
        
        $perPage = 15;
        $search = trim((string) $request->input('search', ''));
        $tab = $request->input('tab', 'sales');
        if (! in_array($tab, ['sales', 'vatable', 'nonvatable', 'disbursements'], true)) {
            $tab = 'sales';
            $request->merge(['tab' => $tab]);
        }
        
        $salesRecords = collect();
        $vatableRecords = collect();
        $nonVatableRecords = collect();
        $disbursementRecords = collect();

        if ($tab === 'sales') {
            $q = MenuOrderPayment::query()
                ->where('payment_date', 'like', $monthYear . '%')
                ->with(['order.branch', 'receivedBy']);
            $q = $this->applyBranchScope($q);
            if ($search !== '') {
                $q->where(function($nested) use ($search) {
                    $nested->where('or_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('order', function($o) use ($search) {
                            $o->where('customer_name', 'like', "%{$search}%");
                        });
                });
            }
            $salesRecords = $q->orderBy('payment_date', 'asc')->paginate($perPage)->appends($request->query());
        } elseif ($tab === 'vatable') {
            $q = VatablePurchase::query()->where('month_year', $monthYear)->with('branch');
            $q = $this->applyBranchScope($q);
            if ($search !== '') {
                $q->where('vendor_name', 'like', "%{$search}%");
            }
            $vatableRecords = $q->orderBy('date', 'asc')->paginate($perPage)->appends($request->query());
        } elseif ($tab === 'nonvatable') {
            $q = NonVatablePurchase::query()->where('month_year', $monthYear)->with('branch');
            $q = $this->applyBranchScope($q);
            if ($search !== '') {
                $q->where('vendor_name', 'like', "%{$search}%");
            }
            $nonVatableRecords = $q->orderBy('date', 'asc')->paginate($perPage)->appends($request->query());
        } elseif ($tab === 'disbursements') {
            $q = CashDisbursement::query()->where('month_year', $monthYear)->with('branch');
            $q = $this->applyBranchScope($q);
            if ($search !== '') {
                $q->where(function ($nested) use ($search) {
                    $nested->where('payee', 'like', "%{$search}%")
                        ->orWhere('check_number', 'like', "%{$search}%");
                });
            }
            $disbursementRecords = $q->orderBy('date', 'asc')->paginate($perPage)->appends($request->query());
        }
        
        $monthLabel = Carbon::createFromFormat('Y-m', $monthYear)->format('F Y');

        return view('expenses.show', compact(
            'monthYear', 'monthLabel', 'selectedBranchId',
            'grossSales', 'netSales', 'vatTotal', 'discountTotal', 'salesByMethod',
            'totalVatable', 'totalNonVatable', 'totalDisbursements',
            'salesRecords', 'vatableRecords', 'nonVatableRecords', 'disbursementRecords'
        ));
    }

    // ─── VATABLE PURCHASES ────────────────────────────────────────────────────

    public function storeVatable(Request $request, string $monthYear)
    {
        $request->validate([
            'date'          => 'nullable|date',
            'vendor_name'   => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'si_number'     => 'nullable|string|max:100',
            'tin'           => 'nullable|string|max:100',
            'gross_amount'  => 'required|numeric|min:0',
            'vat'           => 'required|numeric|min:0',
            'net_purchases' => 'required|numeric|min:0',
        ]);

        VatablePurchase::create([
            'branch_id'     => $this->currentBranchId(),
            'month_year'    => $monthYear,
            'date'          => $request->date ?: null,
            'vendor_name'   => $request->vendor_name,
            'address'       => $request->address,
            'si_number'     => $request->si_number,
            'tin'           => $request->tin,
            'gross_amount'  => $request->gross_amount,
            'vat'           => $request->vat,
            'net_purchases' => $request->net_purchases,
        ]);

        return redirect(route('expenses.show', $monthYear) . '?tab=vatable')
            ->with('success', 'Vatable purchase added successfully.');
    }

    public function updateVatable(Request $request, string $monthYear, VatablePurchase $vatable)
    {
        $branchId = $this->currentBranchId();
        if ($branchId && (int) $vatable->branch_id !== $branchId) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'date'          => 'nullable|date',
            'vendor_name'   => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'si_number'     => 'nullable|string|max:100',
            'tin'           => 'nullable|string|max:100',
            'gross_amount'  => 'required|numeric|min:0',
            'vat'           => 'required|numeric|min:0',
            'net_purchases' => 'required|numeric|min:0',
        ]);

        $vatable->update([
            'date'          => $request->date ?: null,
            'vendor_name'   => $request->vendor_name,
            'address'       => $request->address,
            'si_number'     => $request->si_number,
            'tin'           => $request->tin,
            'gross_amount'  => $request->gross_amount,
            'vat'           => $request->vat,
            'net_purchases' => $request->net_purchases,
        ]);

        return redirect(route('expenses.show', $monthYear) . '?tab=vatable')
            ->with('success', 'Vatable purchase updated successfully.');
    }

    public function destroyVatable(string $monthYear, VatablePurchase $vatable)
    {
        $branchId = $this->currentBranchId();
        if ($branchId && (int) $vatable->branch_id !== $branchId) {
            abort(403, 'Unauthorized.');
        }
        $vatable->delete();
        return redirect(route('expenses.show', $monthYear) . '?tab=vatable')
            ->with('success', 'Record deleted.');
    }

    // ─── NON-VATABLE PURCHASES ────────────────────────────────────────────────

    public function storeNonVatable(Request $request, string $monthYear)
    {
        $request->validate([
            'date'         => 'nullable|date',
            'vendor_name'  => 'required|string|max:255',
            'gross_amount' => 'required|numeric|min:0',
        ]);

        NonVatablePurchase::create([
            'branch_id'    => $this->currentBranchId(),
            'month_year'   => $monthYear,
            'date'         => $request->date ?: null,
            'vendor_name'  => $request->vendor_name,
            'gross_amount' => $request->gross_amount,
        ]);

        return redirect(route('expenses.show', $monthYear) . '?tab=nonvatable')
            ->with('success', 'Non-vatable purchase added successfully.');
    }

    public function updateNonVatable(Request $request, string $monthYear, NonVatablePurchase $nonVatable)
    {
        $branchId = $this->currentBranchId();
        if ($branchId && (int) $nonVatable->branch_id !== $branchId) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'date'         => 'nullable|date',
            'vendor_name'  => 'required|string|max:255',
            'gross_amount' => 'required|numeric|min:0',
        ]);

        $nonVatable->update([
            'date'         => $request->date ?: null,
            'vendor_name'  => $request->vendor_name,
            'gross_amount' => $request->gross_amount,
        ]);

        return redirect(route('expenses.show', $monthYear) . '?tab=nonvatable')
            ->with('success', 'Non-vatable purchase updated successfully.');
    }

    public function destroyNonVatable(string $monthYear, NonVatablePurchase $nonVatable)
    {
        $branchId = $this->currentBranchId();
        if ($branchId && (int) $nonVatable->branch_id !== $branchId) {
            abort(403, 'Unauthorized.');
        }
        $nonVatable->delete();
        return redirect(route('expenses.show', $monthYear) . '?tab=nonvatable')
            ->with('success', 'Record deleted.');
    }

    // ─── CASH DISBURSEMENTS ───────────────────────────────────────────────────

    public function storeDisbursement(Request $request, string $monthYear)
    {
        $request->validate([
            'date'         => 'nullable|date',
            'check_number' => 'nullable|string|max:100',
            'payee'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0',
            'reference'    => 'nullable|string|max:255',
        ]);

        CashDisbursement::create([
            'branch_id'    => $this->currentBranchId(),
            'month_year'   => $monthYear,
            'date'         => $request->date ?: null,
            'check_number' => $request->check_number,
            'payee'        => $request->payee,
            'amount'       => $request->amount,
            'reference'    => $request->reference,
        ]);

        return redirect(route('expenses.show', $monthYear) . '?tab=disbursements')
            ->with('success', 'Disbursement added successfully.');
    }

    public function updateDisbursement(Request $request, string $monthYear, CashDisbursement $disbursement)
    {
        $branchId = $this->currentBranchId();
        if ($branchId && (int) $disbursement->branch_id !== $branchId) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'date'         => 'nullable|date',
            'check_number' => 'nullable|string|max:100',
            'payee'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0',
            'reference'    => 'nullable|string|max:255',
        ]);

        $disbursement->update([
            'date'         => $request->date ?: null,
            'check_number' => $request->check_number,
            'payee'        => $request->payee,
            'amount'       => $request->amount,
            'reference'    => $request->reference,
        ]);

        return redirect(route('expenses.show', $monthYear) . '?tab=disbursements')
            ->with('success', 'Disbursement updated successfully.');
    }

    public function destroyDisbursement(string $monthYear, CashDisbursement $disbursement)
    {
        $branchId = $this->currentBranchId();
        if ($branchId && (int) $disbursement->branch_id !== $branchId) {
            abort(403, 'Unauthorized.');
        }
        $disbursement->delete();
        return redirect(route('expenses.show', $monthYear) . '?tab=disbursements')
            ->with('success', 'Record deleted.');
    }
}
