<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\PurchaseVoucher;
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
                    ->orWhereHas('vendor', fn ($inner) => $inner->where('name', 'like', "%{$s}%"));
            }))
            ->latest('date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('purchase-vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('purchase-vouchers.create', [
            'suggestedApvNo' => PurchaseVoucher::nextApvNo(),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateVoucher($request);

        DB::transaction(function () use ($validated, $request): void {
            $voucher = PurchaseVoucher::create([
                ...Arr::except($validated, 'items'),
                // Always generated server-side so concurrent forms can't collide.
                'apv_no' => PurchaseVoucher::nextApvNo(),
                'branch_id' => $this->activeBranchId($request) ?? ($validated['branch_id'] ?? null),
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($voucher, $validated['items']);
        });

        return redirect()->route('purchase-vouchers.index')->with('success', 'Purchase Voucher (APV) created successfully.');
    }

    public function show(Request $request, PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeBranchRecord($request, $purchaseVoucher->branch_id);
        $purchaseVoucher->load(['items.costAccount', 'vendor', 'creditAccount', 'checkVouchers.checkRegisterEntry']);

        return view('purchase-vouchers.show', compact('purchaseVoucher'));
    }

    public function edit(Request $request, PurchaseVoucher $purchaseVoucher)
    {
        $this->authorizeBranchRecord($request, $purchaseVoucher->branch_id);
        $purchaseVoucher->load('items');

        return view('purchase-vouchers.edit', [
            'purchaseVoucher' => $purchaseVoucher,
            ...$this->formOptions(),
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

        DB::transaction(function () use ($validated, $purchaseVoucher): void {
            $purchaseVoucher->update(Arr::except($validated, ['items', 'apv_no']));
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
            'vendor_id' => ['nullable', 'exists:suppliers,id'],
            'si_no' => ['nullable', 'string', 'max:100'],
            'credit_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('type', 'credit_liability')],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.particulars' => ['required', 'string', 'max:255'],
            'items.*.cost_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('type', 'debit_expense')],
            'items.*.amount_w_vat' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_exempt' => ['nullable', 'numeric', 'min:0'],
            'items.*.non_vat_purchase' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureEachItemHasAnAmount($validated['items']);

        // Only admins browsing all branches may choose the branch explicitly.
        if (! $request->user()->isAdmin()) {
            unset($validated['branch_id']);
        }

        return $validated;
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

    private function formOptions(): array
    {
        return [
            'vendors' => Supplier::orderBy('name')->get(),
            'creditAccounts' => ChartOfAccount::where('type', 'credit_liability')->where('is_active', true)->orderBy('name')->get(),
            'costAccounts' => ChartOfAccount::where('type', 'debit_expense')->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
