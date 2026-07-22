<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\CheckRegister;
use App\Models\CheckVoucher;
use App\Models\PettyCashVoucher;
use App\Models\PurchaseVoucher;
use App\Support\VatCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckVoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = CheckVoucher::query()
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($inner) use ($s): void {
                $inner->where('cv_no', 'like', "%{$s}%")
                    ->orWhere('payee_name', 'like', "%{$s}%")
                    ->orWhereHas('purchaseVoucher', fn ($apv) => $apv->where('apv_no', 'like', "%{$s}%"));
            }));

        $totals = (clone $query)->selectRaw('
            COALESCE(SUM(amount_w_vat), 0) as amount_w_vat,
            COALESCE(SUM(vat), 0) as vat,
            COALESCE(SUM(net_purchases), 0) as net_purchases,
            COALESCE(SUM(vat_exempt), 0) as vat_exempt,
            COALESCE(SUM(non_vat_purchase), 0) as non_vat_purchase,
            COALESCE(SUM(ewt_amount), 0) as ewt_amount,
            COALESCE(SUM(amount_paid), 0) as amount_paid
        ')->first();

        $vouchers = $query
            ->with(['purchaseVoucher', 'checkRegisterEntry', 'costAccount', 'branch'])
            ->latest('date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('check-vouchers.index', compact('vouchers', 'totals'));
    }

    public function create(Request $request)
    {
        $replenishPcvs = collect();
        if ($request->filled('replenish_pcv')) {
            $ids = (array) $request->input('replenish_pcv');
            $replenishPcvs = PettyCashVoucher::whereIn('id', $ids)->whereNull('check_voucher_id')->with('items')->get();
        }

        $payApv = null;
        if ($request->filled('pay_apv')) {
            $payApv = PurchaseVoucher::with(['vendor', 'items'])->find($request->input('pay_apv'));
        }

        return view('check-vouchers.create', [
            'replenishPcvs' => $replenishPcvs,
            'payApv' => $payApv,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');

        $rules = [
            'date' => ['required', 'date'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'cv_no' => ['required', 'string', 'max:50', 'unique:check_vouchers,cv_no'],
            'type' => ['required', Rule::in(['pcf_replenishment', 'apv_payment', 'cod_purchase', 'other_disbursement'])],
            'particulars' => ['required', 'string', 'max:255'],
            'payee_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'si_no' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:50'],
            'ewt_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'remarks' => ['nullable', 'string'],
        ];

        if ($type === 'pcf_replenishment') {
            $rules['petty_cash_voucher_ids'] = ['required', 'array', 'min:1'];
            $rules['petty_cash_voucher_ids.*'] = ['exists:petty_cash_vouchers,id'];
            $rules['amount_w_vat'] = ['required', 'numeric', 'min:0.01'];
        } elseif ($type === 'apv_payment') {
            $rules['purchase_voucher_id'] = ['required', 'exists:purchase_vouchers,id'];
            $rules['amount_w_vat'] = ['required', 'numeric', 'min:0.01'];
        } else {
            $rules['cost_account_id'] = ['required', Rule::exists('chart_of_accounts', 'id')->where('type', 'debit_expense')];
            $rules['amount_w_vat'] = ['nullable', 'numeric', 'min:0'];
            $rules['vat_exempt'] = ['nullable', 'numeric', 'min:0'];
            $rules['non_vat_purchase'] = ['nullable', 'numeric', 'min:0'];
            // Direct disbursements have no source record to inherit a branch from.
            $rules['branch_id'] = [
                Rule::requiredIf(fn () => $request->user()->isAdmin() && ! $request->session()->get('selected_branch_id')),
                'nullable', 'exists:branches,id',
            ];
        }

        $validated = $request->validate($rules);

        $apv = null;
        if ($type === 'apv_payment') {
            $apv = PurchaseVoucher::findOrFail($validated['purchase_voucher_id']);
            $this->authorizeBranchRecord($request, $apv->branch_id);

            if (! in_array($apv->status, ['unpaid', 'partially_paid'], true)) {
                throw ValidationException::withMessages([
                    'purchase_voucher_id' => 'The selected APV is already fully paid.',
                ]);
            }

            $remainingBalance = round((float) $apv->payable_total - (float) $apv->amount_paid, 2);
            if ((float) $validated['amount_w_vat'] - $remainingBalance > 0.01) {
                throw ValidationException::withMessages([
                    'amount_w_vat' => 'Payment amount cannot exceed the APV remaining balance (₱'.number_format($remainingBalance, 2).').',
                ]);
            }
        }

        $pcvs = collect();
        if ($type === 'pcf_replenishment') {
            $pcvs = PettyCashVoucher::whereIn('id', $validated['petty_cash_voucher_ids'])
                ->whereNull('check_voucher_id')
                ->with('items')
                ->get();

            if ($pcvs->count() !== count($validated['petty_cash_voucher_ids'])) {
                throw ValidationException::withMessages([
                    'petty_cash_voucher_ids' => 'One or more selected PCVs are already replenished.',
                ]);
            }

            foreach ($pcvs as $pcv) {
                $this->authorizeBranchRecord($request, $pcv->branch_id);
            }

            $expectedTotal = round((float) $pcvs->sum('total'), 2);
            if (abs($expectedTotal - (float) $validated['amount_w_vat']) > 0.01) {
                throw ValidationException::withMessages([
                    'amount_w_vat' => 'Replenishment amount must equal the PCV items sub-total (₱'.number_format($expectedTotal, 2).').',
                ]);
            }
        }

        // The CV lives in the branch of what it pays; otherwise the active
        // branch, or the branch an all-branches admin picked on the form.
        $branchId = $apv?->branch_id
            ?? ($pcvs->isNotEmpty() ? $pcvs->first()->branch_id : null)
            ?? $this->activeBranchId($request)
            ?? ($request->user()->isAdmin() ? ($validated['branch_id'] ?? null) : null);

        DB::transaction(function () use ($validated, $type, $request, $pcvs, $branchId, $apv): void {
            $amountWVat = (float) ($validated['amount_w_vat'] ?? 0);
            $vatExempt = (float) ($validated['vat_exempt'] ?? 0);
            $nonVat = (float) ($validated['non_vat_purchase'] ?? 0);

            $vatSplit = in_array($type, ['cod_purchase', 'other_disbursement'], true)
                ? VatCalculator::split($amountWVat)
                : ['net_purchases' => 0, 'vat' => 0];

            $checkVoucher = new CheckVoucher([
                'branch_id' => $branchId,
                // APV payments inherit the APV's vendor; otherwise the picked supplier.
                'supplier_id' => $apv?->vendor_id ?? ($validated['supplier_id'] ?? null),
                'date' => $validated['date'],
                'cv_no' => $validated['cv_no'],
                'purchase_voucher_id' => $validated['purchase_voucher_id'] ?? null,
                'type' => $type,
                'particulars' => $validated['particulars'],
                'cost_account_id' => $validated['cost_account_id'] ?? null,
                'payee_name' => $validated['payee_name'],
                'address' => $validated['address'] ?? null,
                'si_no' => $validated['si_no'] ?? null,
                'tin' => $validated['tin'] ?? null,
                'amount_w_vat' => $amountWVat,
                'vat' => $vatSplit['vat'],
                'net_purchases' => $vatSplit['net_purchases'],
                'vat_exempt' => $vatExempt,
                'non_vat_purchase' => $nonVat,
                'ewt_rate' => $validated['ewt_rate'] ?? 0,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $checkVoucher->applyEwt();
            $checkVoucher->save();

            if ($type === 'pcf_replenishment') {
                PettyCashVoucher::whereIn('id', $pcvs->pluck('id'))->update(['check_voucher_id' => $checkVoucher->id]);
            }
        });

        return redirect()->route('check-vouchers.index')->with('success', 'Check Voucher created successfully.');
    }

    public function show(Request $request, CheckVoucher $checkVoucher)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);
        $checkVoucher->load(['purchaseVoucher.vendor', 'pettyCashVouchers.items', 'costAccount', 'checkRegisterEntry']);

        return view('check-vouchers.show', compact('checkVoucher'));
    }

    public function issueCheck(Request $request, CheckVoucher $checkVoucher)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);

        if ($checkVoucher->status !== 'draft') {
            return back()->with('error', 'This Check Voucher already has a check issued against it.');
        }

        $validated = $request->validate([
            'check_date' => ['required', 'date'],
            'check_no' => ['required', 'string', 'max:50', 'unique:check_register,check_no'],
        ]);

        CheckRegister::create([
            'branch_id' => $checkVoucher->branch_id,
            'check_voucher_id' => $checkVoucher->id,
            'check_date' => $validated['check_date'],
            'check_no' => $validated['check_no'],
            'payee' => $checkVoucher->payee_name,
            'particulars' => $checkVoucher->particulars,
            'amount' => $checkVoucher->amount_paid,
            'status' => 'issued',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('check-vouchers.show', $checkVoucher)->with('success', 'Check issued and logged in the Check Register.');
    }

    public function unreplenishedPcvs(Request $request): JsonResponse
    {
        $pcvs = PettyCashVoucher::whereNull('check_voucher_id')
            // Scope to the active branch, but keep legacy records with no branch selectable.
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where(
                fn ($inner) => $inner->where('branch_id', $id)->orWhereNull('branch_id')
            ))
            ->with('items')
            ->orderBy('date')
            ->get()
            ->map(fn (PettyCashVoucher $pcv): array => [
                'id' => $pcv->id,
                'pcv_no' => $pcv->pcv_no,
                'date' => $pcv->date->toDateString(),
                'total' => $pcv->total,
            ]);

        return response()->json($pcvs);
    }

    public function unpaidApvs(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $apvs = PurchaseVoucher::with(['vendor', 'items'])
            // Scope to the active branch, but keep legacy records with no branch selectable.
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where(
                fn ($inner) => $inner->where('branch_id', $id)->orWhereNull('branch_id')
            ))
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->when($search, fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('apv_no', 'like', "%{$s}%")
                    ->orWhereHas('vendor', fn ($inner) => $inner->where('name', 'like', "%{$s}%"));
            }))
            ->orderByDesc('date')
            ->limit(20)
            ->get()
            ->map(fn (PurchaseVoucher $apv): array => [
                'id' => $apv->id,
                'apv_no' => $apv->apv_no,
                'vendor_name' => $apv->vendor?->name,
                'vendor_address' => $apv->vendor?->address,
                'vendor_tin' => $apv->vendor?->tin,
                'payable_total' => $apv->payable_total,
                'amount_paid' => $apv->amount_paid,
                'remaining_balance' => round($apv->payable_total - $apv->amount_paid, 2),
                'status' => $apv->status,
            ]);

        return response()->json($apvs);
    }

    private function formOptions(): array
    {
        return [
            'costAccounts' => ChartOfAccount::where('type', 'debit_expense')->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
        ];
    }
}
