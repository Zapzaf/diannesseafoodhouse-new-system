<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\PettyCashVoucher;
use App\Support\VatCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PettyCashVoucherController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = PettyCashVoucher::with(['items', 'checkVoucher', 'branch', 'supplier'])
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('status') === 'replenished', fn ($q) => $q->whereNotNull('check_voucher_id'))
            ->when($request->input('status') === 'pending', fn ($q) => $q->whereNull('check_voucher_id'))
            ->when($request->input('search'), fn ($q, $s) => $q->where('pcv_no', 'like', "%{$s}%"))
            ->latest('date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('petty-cash-vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('petty-cash-vouchers.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $this->validateVoucher($request);

        DB::transaction(function () use ($validated, $request): void {
            $voucher = PettyCashVoucher::create([
                ...Arr::except($validated, 'items'),
                'branch_id' => $this->activeBranchId($request) ?? ($validated['branch_id'] ?? null),
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($voucher, $validated['items']);
        });

        return redirect()->route('petty-cash-vouchers.index')->with('success', 'Petty Cash Voucher (PCV) created successfully.');
    }

    public function show(Request $request, PettyCashVoucher $pettyCashVoucher)
    {
        $this->authorizeBranchRecord($request, $pettyCashVoucher->branch_id);
        $pettyCashVoucher->load(['items.costAccount', 'checkVoucher.checkRegisterEntry']);

        return view('petty-cash-vouchers.show', compact('pettyCashVoucher'));
    }

    public function edit(Request $request, PettyCashVoucher $pettyCashVoucher)
    {
        $this->authorizeBranchRecord($request, $pettyCashVoucher->branch_id);
        $this->ensureNotReplenished($pettyCashVoucher);
        $pettyCashVoucher->load('items');

        return view('petty-cash-vouchers.edit', [
            'pettyCashVoucher' => $pettyCashVoucher,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, PettyCashVoucher $pettyCashVoucher)
    {
        $this->authorizeBranchRecord($request, $pettyCashVoucher->branch_id);
        $this->ensureNotReplenished($pettyCashVoucher);

        $validated = $this->validateVoucher($request, $pettyCashVoucher);

        DB::transaction(function () use ($validated, $pettyCashVoucher): void {
            $pettyCashVoucher->update(Arr::except($validated, 'items'));
            $pettyCashVoucher->items()->delete();
            $this->syncItems($pettyCashVoucher, $validated['items']);
        });

        return redirect()->route('petty-cash-vouchers.index')->with('success', 'Petty Cash Voucher (PCV) updated successfully.');
    }

    public function destroy(Request $request, PettyCashVoucher $pettyCashVoucher)
    {
        $this->authorizeBranchRecord($request, $pettyCashVoucher->branch_id);
        $this->ensureNotReplenished($pettyCashVoucher);
        $pettyCashVoucher->delete();

        return redirect()->route('petty-cash-vouchers.index')->with('success', 'Petty Cash Voucher (PCV) deleted successfully.');
    }

    private function ensureNotReplenished(PettyCashVoucher $pettyCashVoucher): void
    {
        if ($pettyCashVoucher->isReplenished()) {
            throw ValidationException::withMessages([
                'pcv_no' => 'This PCV has already been replenished via a Check Voucher and can no longer be changed.',
            ]);
        }
    }

    private function validateVoucher(Request $request, ?PettyCashVoucher $pettyCashVoucher = null): array
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'branch_id' => [
                Rule::requiredIf(fn () => $request->user()->isAdmin() && ! $request->session()->get('selected_branch_id')),
                'nullable', 'exists:branches,id',
            ],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'pcv_no' => [
                'required', 'string', 'max:50',
                Rule::unique('petty_cash_vouchers', 'pcv_no')->ignore($pettyCashVoucher?->id),
            ],
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

    private function syncItems(PettyCashVoucher $voucher, array $items): void
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
            'costAccounts' => ChartOfAccount::where('type', 'debit_expense')->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
        ];
    }
}
