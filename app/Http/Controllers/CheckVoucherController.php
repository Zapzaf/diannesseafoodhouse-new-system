<?php

namespace App\Http\Controllers;

use App\Exports\DisbursementReportExport;
use App\Models\AdvanceLiquidation;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CheckRegister;
use App\Models\CheckVoucher;
use App\Models\CheckVoucherReceipt;
use App\Models\PettyCashVoucher;
use App\Models\PurchaseVoucher;
use App\Models\Service;
use App\Support\VatCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CheckVoucherController extends Controller
{
    private function filteredQuery(Request $request)
    {
        return CheckVoucher::query()
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($inner) use ($s): void {
                $inner->where('cv_no', 'like', "%{$s}%")
                    ->orWhere('reference_no', 'like', "%{$s}%")
                    ->orWhere('payee_name', 'like', "%{$s}%")
                    ->orWhereHas('purchaseVoucher', fn ($apv) => $apv->where('apv_no', 'like', "%{$s}%"))
                    ->orWhereHas('service', fn ($ser) => $ser->where('ref_no', 'like', "%{$s}%"));
            }));
    }

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

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
            ->withCount('liquidations')
            ->latest('date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('check-vouchers.index', compact('vouchers', 'totals'));
    }

    /**
     * Excel export of Disbursements (Check Vouchers), honoring the same
     * type/status/search/branch filters as the index list, with the
     * creating/updating user included per the client's request.
     */
    public function export(Request $request)
    {
        $vouchers = $this->filteredQuery($request)
            ->with(['branch', 'supplier', 'creator', 'updater'])
            ->latest('date')
            ->get();

        $filename = 'disbursements-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new DisbursementReportExport($vouchers), $filename);
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

        $payService = null;
        if ($request->filled('pay_service')) {
            $payService = Service::with(['supplier'])->find($request->input('pay_service'));
        }

        return view('check-vouchers.create', [
            'replenishPcvs' => $replenishPcvs,
            'payApv' => $payApv,
            'payService' => $payService,
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');

        // The create form can't distinguish "typo, pick another number" from "I meant
        // to add another receipt to that existing CV" — so point the user at the
        // existing CV's Add Receipt feature instead of a bare "already taken" error.
        if ($request->filled('cv_no') && $type) {
            $duplicate = CheckVoucher::where('type', $type)->where('cv_no', $request->input('cv_no'))->first();
            if ($duplicate) {
                return back()->withInput()->withErrors([
                    'cv_no' => 'CV # '.$duplicate->cv_no.' already exists for this Disbursement Type.',
                ])->with('duplicate_cv', $duplicate);
            }
        }

        $rules = [
            'date' => ['required', 'date'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            // CV numbers only need to be unique within their own Disbursement Type —
            // the same number (e.g. 2595) can be reused across different types.
            'cv_no' => ['required', 'string', 'max:50', Rule::unique('check_vouchers', 'cv_no')->where(fn ($q) => $q->where('type', $type))],
            'type' => ['required', Rule::in(['pcf_replenishment', 'apv_payment', 'service_payment', 'cod_purchase', 'advance', 'other_disbursement'])],
            'particulars' => ['required', 'string', 'max:255'],
            'payee_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'si_no' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:50'],
            'ewt_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'payment_method' => ['required', Rule::in(['cash', 'check', 'bank_transfer', 'online'])],
            'remarks' => ['nullable', 'string'],
        ];

        if ($type === 'pcf_replenishment') {
            $rules['petty_cash_voucher_ids'] = ['required', 'array', 'min:1'];
            $rules['petty_cash_voucher_ids.*'] = ['exists:petty_cash_vouchers,id'];
            $rules['amount_w_vat'] = ['required', 'numeric', 'min:0.01'];
        } elseif ($type === 'apv_payment') {
            $rules['purchase_voucher_id'] = ['required', 'exists:purchase_vouchers,id'];
            $rules['amount_w_vat'] = ['required', 'numeric', 'min:0.01'];
        } elseif ($type === 'service_payment') {
            $rules['service_id'] = ['required', 'exists:services,id'];
            $rules['amount_w_vat'] = ['required', 'numeric', 'min:0.01'];
        } elseif ($type === 'advance') {
            $rules['advance_account_id'] = ['required', Rule::exists('chart_of_accounts', 'id')->whereIn('type', ['credit_liability', 'debit_asset'])];
            $rules['amount_w_vat'] = ['required', 'numeric', 'min:0.01'];
            $rules['advance_vat_type'] = ['required', Rule::in(['with_vat', 'without_vat'])];
            $rules['branch_id'] = [
                Rule::requiredIf(fn () => $request->user()->isAdmin() && ! $request->session()->get('selected_branch_id')),
                'nullable', 'exists:branches,id',
            ];
        } else {
            $rules['cost_account_id'] = ['required', Rule::exists('chart_of_accounts', 'id')->whereIn('type', ['debit_expense', 'debit_asset'])];
            // A single CV commonly settles several supplier receipts in one check.
            $rules['receipts'] = ['required', 'array', 'min:1'];
            $rules['receipts.*.si_no'] = ['nullable', 'string', 'max:100'];
            $rules['receipts.*.supplier_id'] = ['nullable', 'exists:suppliers,id'];
            $rules['receipts.*.amount_w_vat'] = ['nullable', 'numeric', 'min:0'];
            $rules['receipts.*.vat_exempt'] = ['nullable', 'numeric', 'min:0'];
            $rules['receipts.*.non_vat_purchase'] = ['nullable', 'numeric', 'min:0'];
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

        $service = null;
        if ($type === 'service_payment') {
            $service = Service::findOrFail($validated['service_id']);
            $this->authorizeBranchRecord($request, $service->branch_id);

            if (! in_array($service->status, ['unpaid', 'partially_paid'], true)) {
                throw ValidationException::withMessages([
                    'service_id' => 'The selected Service is already fully paid.',
                ]);
            }

            $remainingBalance = round((float) $service->payable_total - (float) $service->amount_paid, 2);
            if ((float) $validated['amount_w_vat'] - $remainingBalance > 0.01) {
                throw ValidationException::withMessages([
                    'amount_w_vat' => 'Payment amount cannot exceed the Service remaining balance (₱'.number_format($remainingBalance, 2).').',
                ]);
            }
        }

        $receipts = collect();
        if (in_array($type, ['cod_purchase', 'other_disbursement'], true)) {
            $receipts = collect($validated['receipts'])->map(fn (array $r): array => [
                'si_no' => $r['si_no'] ?? null,
                'supplier_id' => $r['supplier_id'] ?? null,
                'amount_w_vat' => (float) ($r['amount_w_vat'] ?? 0),
                'vat_exempt' => (float) ($r['vat_exempt'] ?? 0),
                'non_vat_purchase' => (float) ($r['non_vat_purchase'] ?? 0),
            ]);

            $receiptsTotal = round($receipts->sum(fn (array $r) => $r['amount_w_vat'] + $r['vat_exempt'] + $r['non_vat_purchase']), 2);
            if ($receiptsTotal <= 0) {
                throw ValidationException::withMessages([
                    'receipts' => 'At least one receipt must have an amount greater than zero.',
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
            ?? $service?->branch_id
            ?? ($pcvs->isNotEmpty() ? $pcvs->first()->branch_id : null)
            ?? $this->activeBranchId($request)
            ?? ($request->user()->isAdmin() ? ($validated['branch_id'] ?? null) : null);

        DB::transaction(function () use ($validated, $type, $request, $pcvs, $receipts, $branchId, $apv, $service): void {
            $isStandalone = in_array($type, ['cod_purchase', 'other_disbursement'], true);

            // Standalone CVs (COD / Other) derive their totals from the attached
            // receipts, since one payment commonly covers several supplier receipts.
            $amountWVat = $isStandalone ? round($receipts->sum('amount_w_vat'), 2) : (float) ($validated['amount_w_vat'] ?? 0);
            $vatExempt = $isStandalone ? round($receipts->sum('vat_exempt'), 2) : (float) ($validated['vat_exempt'] ?? 0);
            $nonVat = $isStandalone ? round($receipts->sum('non_vat_purchase'), 2) : (float) ($validated['non_vat_purchase'] ?? 0);

            $vatSplit = match (true) {
                $isStandalone => VatCalculator::split($amountWVat),
                $type === 'advance' && ($validated['advance_vat_type'] ?? 'without_vat') === 'with_vat' => VatCalculator::split($amountWVat),
                default => ['net_purchases' => 0, 'vat' => 0],
            };

            $siNo = $isStandalone
                ? $receipts->pluck('si_no')->filter()->implode(', ') ?: null
                : ($validated['si_no'] ?? null);

            $checkVoucher = new CheckVoucher([
                'branch_id' => $branchId,
                // APV/Service payments inherit their parent's vendor; otherwise the picked supplier.
                'supplier_id' => $apv?->vendor_id ?? $service?->supplier_id ?? ($validated['supplier_id'] ?? null),
                'date' => $validated['date'],
                'cv_no' => $validated['cv_no'],
                'reference_no' => CheckVoucher::nextDisbursementNo(),
                'purchase_voucher_id' => $validated['purchase_voucher_id'] ?? null,
                'service_id' => $validated['service_id'] ?? null,
                'advance_account_id' => $validated['advance_account_id'] ?? null,
                'type' => $type,
                'particulars' => $validated['particulars'],
                'cost_account_id' => $validated['cost_account_id'] ?? null,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'payment_method' => $validated['payment_method'],
                'payee_name' => $validated['payee_name'],
                'address' => $validated['address'] ?? null,
                'si_no' => $siNo,
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

            foreach ($receipts as $receipt) {
                $receiptSplit = VatCalculator::split($receipt['amount_w_vat']);
                $checkVoucher->receipts()->create([
                    'si_no' => $receipt['si_no'],
                    'supplier_id' => $receipt['supplier_id'],
                    'amount_w_vat' => $receipt['amount_w_vat'],
                    'vat' => $receiptSplit['vat'],
                    'net_purchases' => $receiptSplit['net_purchases'],
                    'vat_exempt' => $receipt['vat_exempt'],
                    'non_vat_purchase' => $receipt['non_vat_purchase'],
                ]);
            }
        });

        return redirect()->route('check-vouchers.index')->with('success', 'Check Voucher created successfully.');
    }

    /**
     * Admin-only: delete the entire Check Voucher — any disbursement type,
     * whether it's still a draft or already paid out — per the client's
     * request for a straightforward Delete action on CVs (e.g. CV#2613,
     * CV#2612). Related records clean up automatically via DB cascades:
     * its Check Register entry, receipts (COD/Other), and advance
     * liquidations are all deleted with it; any Petty Cash Vouchers it
     * replenished are simply unlinked (their own PCV record stays intact).
     *
     * The one hard stop is an Advance with liquidation(s) already recorded
     * against it — those are real recorded expenses, not something to
     * silently cascade away. Void it instead in that case so the audit
     * trail stays intact.
     *
     * If the CV settled a Purchase Voucher or Service, that parent's
     * paid/partially-paid/unpaid status is recomputed afterward so it
     * doesn't keep counting a payment that no longer exists.
     */
    public function destroy(Request $request, CheckVoucher $checkVoucher)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);

        if ($checkVoucher->type === 'advance' && $checkVoucher->liquidations()->exists()) {
            return back()->with('error', 'This advance already has liquidation(s) recorded and cannot be deleted. Void it instead if it was recorded in error.');
        }

        $purchaseVoucher = $checkVoucher->purchaseVoucher;
        $service = $checkVoucher->service;
        $cvNo = $checkVoucher->cv_no;

        DB::transaction(function () use ($checkVoucher): void {
            $checkVoucher->delete();
        });

        $purchaseVoucher?->recomputeStatus();
        $service?->recomputeStatus();

        return redirect()
            ->to(url()->previous() === route('check-vouchers.show', $checkVoucher) ? route('check-vouchers.index') : url()->previous())
            ->with('success', 'Check Voucher '.$cvNo.' deleted.');
    }

    public function show(Request $request, CheckVoucher $checkVoucher)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);
        $checkVoucher->load(['purchaseVoucher.vendor', 'service.supplier', 'pettyCashVouchers.items', 'costAccount', 'bankAccount', 'advanceAccount', 'checkRegisterEntry', 'receipts.supplier', 'liquidations', 'attachments']);
        $suppliers = \App\Models\Supplier::orderBy('name')->get();

        return view('check-vouchers.show', compact('checkVoucher', 'suppliers'));
    }

    /**
     * Append another receipt/invoice to an existing CV — the client's workflow is
     * to reuse the same CV # for several receipts belonging to one payment, not to
     * create a second Check Voucher with a duplicate number.
     */
    public function addReceipt(Request $request, CheckVoucher $checkVoucher)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);
        $this->guardReceiptEditable($checkVoucher);

        $validated = $request->validate([
            'si_no' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'amount_w_vat' => ['nullable', 'numeric', 'min:0'],
            'vat_exempt' => ['nullable', 'numeric', 'min:0'],
            'non_vat_purchase' => ['nullable', 'numeric', 'min:0'],
        ]);

        $amountWVat = (float) ($validated['amount_w_vat'] ?? 0);
        $vatExempt = (float) ($validated['vat_exempt'] ?? 0);
        $nonVat = (float) ($validated['non_vat_purchase'] ?? 0);

        if ($amountWVat + $vatExempt + $nonVat <= 0) {
            throw ValidationException::withMessages([
                'amount_w_vat' => 'The receipt must have an amount greater than zero.',
            ]);
        }

        DB::transaction(function () use ($checkVoucher, $validated, $amountWVat, $vatExempt, $nonVat): void {
            $split = VatCalculator::split($amountWVat);
            $checkVoucher->receipts()->create([
                'si_no' => $validated['si_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'amount_w_vat' => $amountWVat,
                'vat' => $split['vat'],
                'net_purchases' => $split['net_purchases'],
                'vat_exempt' => $vatExempt,
                'non_vat_purchase' => $nonVat,
            ]);

            $this->recalculateFromReceipts($checkVoucher);
        });

        return back()->with('success', 'Receipt added to Check Voucher '.$checkVoucher->cv_no.'.');
    }

    public function updateReceipt(Request $request, CheckVoucher $checkVoucher, CheckVoucherReceipt $receipt)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);
        $this->guardReceiptEditable($checkVoucher);
        abort_if($receipt->check_voucher_id !== $checkVoucher->id, 404);

        $validated = $request->validate([
            'si_no' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'amount_w_vat' => ['nullable', 'numeric', 'min:0'],
            'vat_exempt' => ['nullable', 'numeric', 'min:0'],
            'non_vat_purchase' => ['nullable', 'numeric', 'min:0'],
        ]);

        $amountWVat = (float) ($validated['amount_w_vat'] ?? 0);
        $vatExempt = (float) ($validated['vat_exempt'] ?? 0);
        $nonVat = (float) ($validated['non_vat_purchase'] ?? 0);

        if ($amountWVat + $vatExempt + $nonVat <= 0) {
            throw ValidationException::withMessages([
                'amount_w_vat' => 'The receipt must have an amount greater than zero.',
            ]);
        }

        DB::transaction(function () use ($receipt, $checkVoucher, $validated, $amountWVat, $vatExempt, $nonVat): void {
            $split = VatCalculator::split($amountWVat);
            $receipt->update([
                'si_no' => $validated['si_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'amount_w_vat' => $amountWVat,
                'vat' => $split['vat'],
                'net_purchases' => $split['net_purchases'],
                'vat_exempt' => $vatExempt,
                'non_vat_purchase' => $nonVat,
            ]);

            $this->recalculateFromReceipts($checkVoucher);
        });

        return back()->with('success', 'Receipt updated.');
    }

    public function deleteReceipt(Request $request, CheckVoucher $checkVoucher, CheckVoucherReceipt $receipt)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);
        $this->guardReceiptEditable($checkVoucher);
        abort_if($receipt->check_voucher_id !== $checkVoucher->id, 404);

        if ($checkVoucher->receipts()->count() <= 1) {
            return back()->with('error', 'A Check Voucher must keep at least one receipt — delete the whole CV instead.');
        }

        DB::transaction(function () use ($receipt, $checkVoucher): void {
            $receipt->delete();
            $this->recalculateFromReceipts($checkVoucher);
        });

        return back()->with('success', 'Receipt removed.');
    }

    /**
     * Only standalone CVs (COD / Other Disbursement) carry the receipts table —
     * PCF replenishments and APV payments derive their amount from the PCVs/APV
     * they settle instead.
     */
    private function guardReceiptEditable(CheckVoucher $checkVoucher): void
    {
        if (! in_array($checkVoucher->type, ['cod_purchase', 'other_disbursement'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Receipts can only be managed on COD Purchase / Other Disbursement Check Vouchers.',
            ]);
        }
    }

    private function recalculateFromReceipts(CheckVoucher $checkVoucher): void
    {
        $receipts = $checkVoucher->receipts()->get();

        $checkVoucher->amount_w_vat = round((float) $receipts->sum('amount_w_vat'), 2);
        $checkVoucher->vat_exempt = round((float) $receipts->sum('vat_exempt'), 2);
        $checkVoucher->non_vat_purchase = round((float) $receipts->sum('non_vat_purchase'), 2);
        $split = VatCalculator::split((float) $checkVoucher->amount_w_vat);
        $checkVoucher->vat = $split['vat'];
        $checkVoucher->net_purchases = $split['net_purchases'];
        $checkVoucher->si_no = $receipts->pluck('si_no')->filter()->implode(', ') ?: null;
        $checkVoucher->applyEwt();
        $checkVoucher->save();

        if ($checkVoucher->checkRegisterEntry) {
            $checkVoucher->checkRegisterEntry->update(['amount' => $checkVoucher->amount_paid]);
        }
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

    /**
     * Non-check disbursements (cash, bank transfer, online) have no physical check
     * to log in the Check Register, so this is their equivalent of issueCheck() —
     * flips the CV from draft to issued and recomputes whatever it settles.
     */
    public function markPaid(Request $request, CheckVoucher $checkVoucher)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);

        if ($checkVoucher->payment_method === 'check') {
            throw ValidationException::withMessages([
                'payment_method' => 'Check disbursements must be marked paid by issuing a check.',
            ]);
        }

        if ($checkVoucher->status !== 'draft') {
            return back()->with('error', 'This disbursement has already been marked as paid.');
        }

        $checkVoucher->update(['status' => 'issued']);

        if ($checkVoucher->purchase_voucher_id) {
            $checkVoucher->purchaseVoucher->recomputeStatus();
        }

        if ($checkVoucher->service_id) {
            $checkVoucher->service->recomputeStatus();
        }

        return redirect()->route('check-vouchers.show', $checkVoucher)->with('success', 'Disbursement marked as paid.');
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

    public function unpaidServices(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $services = Service::with(['supplier'])
            // Scope to the active branch, but keep legacy records with no branch selectable.
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where(
                fn ($inner) => $inner->where('branch_id', $id)->orWhereNull('branch_id')
            ))
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->when($search, fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('ref_no', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($inner) => $inner->where('name', 'like', "%{$s}%"));
            }))
            ->orderByDesc('date')
            ->limit(20)
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'ref_no' => $service->ref_no,
                'supplier_name' => $service->supplier?->name,
                'payor' => $service->payor,
                'payable_total' => $service->payable_total,
                'amount_paid' => $service->amount_paid,
                'remaining_balance' => round($service->payable_total - $service->amount_paid, 2),
                'status' => $service->status,
            ]);

        return response()->json($services);
    }

    public function liquidateAdvance(Request $request, CheckVoucher $checkVoucher)
    {
        $this->authorizeBranchRecord($request, $checkVoucher->branch_id);

        if ($checkVoucher->type !== 'advance') {
            throw ValidationException::withMessages([
                'type' => 'Only Advance disbursements can be liquidated.',
            ]);
        }

        if ($checkVoucher->status === 'draft') {
            throw ValidationException::withMessages([
                'type' => 'This advance has not been paid out yet — mark it as paid before recording a liquidation.',
            ]);
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('type', 'debit_expense')],
            'remarks' => ['nullable', 'string'],
        ]);

        $outstanding = $checkVoucher->outstanding_advance;
        if ((float) $validated['amount'] - $outstanding > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Liquidation amount cannot exceed the outstanding advance (₱'.number_format($outstanding, 2).').',
            ]);
        }

        AdvanceLiquidation::create([
            ...$validated,
            'check_voucher_id' => $checkVoucher->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Advance liquidation recorded.');
    }

    private function formOptions(Request $request): array
    {
        return [
            'costAccounts' => ChartOfAccount::whereIn('type', ['debit_expense', 'debit_asset'])->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'advanceAccounts' => ChartOfAccount::whereIn('type', ['credit_liability', 'debit_asset'])->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)
                ->when($this->activeBranchId($request), fn ($q, $id) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $id)))
                ->orderBy('bank_name')->get(),
        ];
    }
}
