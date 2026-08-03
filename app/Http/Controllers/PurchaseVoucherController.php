<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CheckVoucher;
use App\Models\PurchaseVoucher;
use App\Models\Service;
use App\Models\Supplier;
use App\Support\VatCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseVoucherController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = PurchaseVoucher::with(['vendor', 'creditAccount', 'items', 'branch'])
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('apv_no', 'like', "%{$s}%")
                    ->orWhere('buyer', 'like', "%{$s}%")
                    ->orWhere('si_no', 'like', "%{$s}%")
                    ->orWhereHas('vendor', fn ($inner) => $inner->where('name', 'like', "%{$s}%"));
            }))
            ->latest('date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('purchase-vouchers.index', compact('vouchers'));
    }

    public function create(Request $request)
    {
        return view('purchase-vouchers.create', [
            'suggestedApvNo' => PurchaseVoucher::nextApvNo(),
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateVoucher($request);

        DB::transaction(function () use ($validated, $request): void {
            $voucher = PurchaseVoucher::create([
                ...Arr::except($validated, ['items', 'bank_account_id', 'payment_method', 'allow_duplicate_invoice']),
                // Always generated server-side so concurrent forms can't collide.
                'apv_no' => PurchaseVoucher::nextApvNo(),
                'branch_id' => $this->activeBranchId($request) ?? ($validated['branch_id'] ?? null),
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($voucher, $validated['items']);

            // COD purchases still live in the Purchase Module's history, but are
            // immediately settled — auto-create the matching disbursement so the
            // Cr Cash-in-Bank side is recorded through the normal CV/bank flow.
            if ($validated['purchase_type'] === 'cod') {
                $this->createCodDisbursement($voucher, $validated, $request);
            }
        });

        return redirect()->route('purchase-vouchers.index')->with('success', 'Purchase Voucher (APV) created successfully.');
    }

    private function createCodDisbursement(PurchaseVoucher $voucher, array $validated, Request $request): void
    {
        $voucher->refresh()->load('items');
        // Sum each component separately — payable_total blends all three together,
        // which would mislabel VAT-exempt/non-VAT peso amounts as taxable if fed
        // straight into VatCalculator::split().
        $amountWVat = (float) $voucher->items->sum('amount_w_vat');
        $vatExempt = (float) $voucher->items->sum('vat_exempt');
        $nonVat = (float) $voucher->items->sum('non_vat_purchase');
        $vatSplit = VatCalculator::split($amountWVat);
        $referenceNo = CheckVoucher::nextDisbursementNo();

        $checkVoucher = new CheckVoucher([
            'branch_id' => $voucher->branch_id,
            'supplier_id' => $voucher->vendor_id,
            'date' => $voucher->date,
            'cv_no' => $referenceNo,
            'reference_no' => $referenceNo,
            'purchase_voucher_id' => $voucher->id,
            'type' => 'cod_purchase',
            'status' => 'issued',
            'particulars' => 'COD Purchase — '.$voucher->apv_no,
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'payee_name' => $voucher->vendor?->name ?? $voucher->buyer,
            'si_no' => $voucher->si_no,
            'amount_w_vat' => $amountWVat,
            'vat' => $vatSplit['vat'],
            'net_purchases' => $vatSplit['net_purchases'],
            'vat_exempt' => $vatExempt,
            'non_vat_purchase' => $nonVat,
            'created_by' => $request->user()->id,
        ]);
        $checkVoucher->applyEwt();
        $checkVoucher->save();

        $voucher->recomputeStatus();
    }

    public function show(Request $request, PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeBranchRecord($request, $purchaseVoucher->branch_id);
        $purchaseVoucher->load(['items.costAccount', 'vendor', 'creditAccount', 'checkVouchers.checkRegisterEntry', 'attachments']);

        return view('purchase-vouchers.show', compact('purchaseVoucher'));
    }

    public function edit(Request $request, PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeBranchRecord($request, $purchaseVoucher->branch_id);
        $purchaseVoucher->load('items');

        return view('purchase-vouchers.edit', [
            'purchaseVoucher' => $purchaseVoucher,
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeBranchRecord($request, $purchaseVoucher->branch_id);

        if ($purchaseVoucher->status !== 'unpaid') {
            throw ValidationException::withMessages([
                'apv_no' => 'This APV has payments recorded against it and can no longer be edited.',
            ]);
        }

        $validated = $this->validateVoucher($request, $purchaseVoucher);

        DB::transaction(function () use ($validated, $purchaseVoucher, $request): void {
            $purchaseVoucher->update([
                ...Arr::except($validated, ['items', 'apv_no', 'bank_account_id', 'payment_method', 'allow_duplicate_invoice']),
                'updated_by' => $request->user()->id,
            ]);
            $purchaseVoucher->items()->delete();
            $this->syncItems($purchaseVoucher, $validated['items']);
        });

        return redirect()->route('purchase-vouchers.index')->with('success', 'Purchase Voucher (APV) updated successfully.');
    }

    public function destroy(Request $request, PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeBranchRecord($request, $purchaseVoucher->branch_id);

        if ($purchaseVoucher->status !== 'unpaid') {
            return back()->with('error', 'This APV has payments recorded against it and cannot be deleted.');
        }

        $purchaseVoucher->delete();

        return redirect()->route('purchase-vouchers.index')->with('success', 'Purchase Voucher (APV) deleted successfully.');
    }

    private function validateVoucher(Request $request, ?PurchaseVoucher $purchaseVoucher = null): array
    {
        $purchaseType = $request->input('purchase_type', 'credit');

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'branch_id' => [
                Rule::requiredIf(fn () => $request->user()->isAdmin() && ! $request->session()->get('selected_branch_id')),
                'nullable', 'exists:branches,id',
            ],
            // Auto-generated on create; kept unchanged on update.
            'apv_no' => [
                'nullable', 'string', 'max:50',
                Rule::unique('purchase_vouchers', 'apv_no')->ignore($purchaseVoucher?->id),
            ],
            'purchase_type' => ['required', Rule::in(['credit', 'cod'])],
            'vendor_id' => ['nullable', 'exists:suppliers,id'],
            'buyer' => ['required', 'string', 'max:255'],
            'si_no' => ['nullable', 'string', 'max:100'],
            'allow_duplicate_invoice' => ['nullable', 'boolean'],
            'credit_account_id' => [
                Rule::requiredIf(fn () => $purchaseType === 'credit'),
                'nullable', Rule::exists('chart_of_accounts', 'id')->where('type', 'credit_liability'),
            ],
            'bank_account_id' => [Rule::requiredIf(fn () => $purchaseType === 'cod'), 'nullable', 'exists:bank_accounts,id'],
            'payment_method' => [Rule::requiredIf(fn () => $purchaseType === 'cod'), 'nullable', Rule::in(['cash', 'bank_transfer', 'online'])],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.particulars' => ['required', 'string', 'max:255'],
            'items.*.cost_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->whereIn('type', ['debit_expense', 'debit_asset'])],
            'items.*.amount_w_vat' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_exempt' => ['nullable', 'numeric', 'min:0'],
            'items.*.non_vat_purchase' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureEachItemHasAnAmount($validated['items']);
        $this->ensureInvoiceNotDuplicated($validated, $purchaseVoucher);

        // Only admins browsing all branches may choose the branch explicitly.
        if (! $request->user()->isAdmin()) {
            unset($validated['branch_id']);
        }

        return $validated;
    }

    /**
     * Prevent the same supplier's invoice from being recorded twice unless the
     * user explicitly opts in (e.g. a supplier really did issue a duplicate SI #).
     */
    private function ensureInvoiceNotDuplicated(array $validated, ?PurchaseVoucher $purchaseVoucher): void
    {
        if (empty($validated['si_no']) || empty($validated['vendor_id']) || ! empty($validated['allow_duplicate_invoice'])) {
            return;
        }

        $exists = PurchaseVoucher::where('vendor_id', $validated['vendor_id'])
            ->where('si_no', $validated['si_no'])
            ->when($purchaseVoucher, fn ($q) => $q->whereKeyNot($purchaseVoucher->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'si_no' => 'This supplier already has a Purchase recorded with Invoice # '.$validated['si_no'].'. Check "allow duplicate" if this is intentional.',
            ]);
        }
    }

    private function ensureEachItemHasAnAmount(array $items): void
    {
        $errors = [];

        foreach ($items as $index => $row) {
            $total = (float) ($row['amount_w_vat'] ?? 0)
                + (float) ($row['vat_exempt'] ?? 0)
                + (float) ($row['non_vat_purchase'] ?? 0);

            if ($total <= 0) {
                $errors["items.{$index}.amount_w_vat"] = 'Each item needs an amount (VAT, VAT-exempt, or non-VAT).';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function syncItems(PurchaseVoucher $voucher, array $items): void
    {
        foreach ($items as $row) {
            $amountWVat = (float) ($row['amount_w_vat'] ?? 0);
            $vatSplit = VatCalculator::split($amountWVat);

            $voucher->items()->create([
                'quantity' => $row['quantity'] ?? null,
                'unit' => $row['unit'] ?? null,
                'particulars' => $row['particulars'],
                'cost_account_id' => $row['cost_account_id'],
                'amount_w_vat' => $amountWVat,
                'vat' => $vatSplit['vat'],
                'net_purchases' => $vatSplit['net_purchases'],
                'vat_exempt' => $row['vat_exempt'] ?? 0,
                'non_vat_purchase' => $row['non_vat_purchase'] ?? 0,
                'remarks' => $row['remarks'] ?? null,
            ]);
        }
    }

    private function formOptions(Request $request): array
    {
        return [
            'vendors' => Supplier::orderBy('name')->get(),
            'creditAccounts' => ChartOfAccount::where('type', 'credit_liability')->where('is_active', true)->orderBy('name')->get(),
            'costAccounts' => ChartOfAccount::whereIn('type', ['debit_expense', 'debit_asset'])->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)
                ->when($this->activeBranchId($request), fn ($q, $id) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $id)))
                ->orderBy('bank_name')->get(),
        ];
    }
}
