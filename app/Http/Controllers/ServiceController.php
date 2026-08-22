<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CheckVoucher;
use App\Models\Service;
use App\Models\Supplier;
use App\Support\VatCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::with(['supplier', 'expenseAccount', 'branch'])
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('date_from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->input('date_to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('ref_no', 'like', "%{$s}%")
                    ->orWhere('payor', 'like', "%{$s}%")
                    ->orWhere('si_no', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($inner) => $inner->where('name', 'like', "%{$s}%"));
            }))
            ->latest('date')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('services.index', compact('services'));
    }

    public function create(Request $request)
    {
        return view('services.create', [
            'suggestedSerNo' => Service::nextSerNo(),
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateService($request);
        $refNo = null;

        DB::transaction(function () use ($validated, $request, &$refNo): void {
            $vatSplit = VatCalculator::split((float) ($validated['amount_w_vat'] ?? 0));
            $refNo = Service::nextSerNo();

            $service = Service::create([
                ...Arr::except($validated, ['bank_account_id', 'payment_method', 'allow_duplicate_invoice']),
                'ref_no' => $refNo,
                'branch_id' => $this->activeBranchId($request) ?? ($validated['branch_id'] ?? null),
                'vat' => $vatSplit['vat'],
                'net_purchases' => $vatSplit['net_purchases'],
                'vat_exempt' => $validated['vat_exempt'] ?? 0,
                'non_vat_purchase' => $validated['non_vat_purchase'] ?? 0,
                'created_by' => $request->user()->id,
            ]);

            if ($validated['service_payment_type'] === 'immediate') {
                $this->createImmediateDisbursement($service, $validated, $request);
            }
        });

        return redirect()->route('services.index')->with('success', 'Service ('.$refNo.') recorded successfully.');
    }

    private function createImmediateDisbursement(Service $service, array $validated, Request $request): void
    {
        $service->refresh();
        // Use the Service's own stored components directly — payable_total blends
        // amount_w_vat + vat_exempt + non_vat_purchase together, which would
        // mislabel VAT-exempt/non-VAT peso amounts as taxable if re-split here.
        $amountWVat = (float) $service->amount_w_vat;
        $vatExempt = (float) $service->vat_exempt;
        $nonVat = (float) $service->non_vat_purchase;
        $vatSplit = VatCalculator::split($amountWVat);
        $referenceNo = CheckVoucher::nextDisbursementNo();

        $checkVoucher = new CheckVoucher([
            'branch_id' => $service->branch_id,
            'supplier_id' => $service->supplier_id,
            'date' => $service->date,
            'cv_no' => $referenceNo,
            'reference_no' => $referenceNo,
            'service_id' => $service->id,
            'type' => 'service_cod',
            'status' => 'issued',
            'particulars' => 'Service Payment — '.$service->ref_no,
            'cost_account_id' => $service->expense_account_id,
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'payee_name' => $service->supplier?->name ?? $service->payor,
            'si_no' => $service->si_no,
            'amount_w_vat' => $amountWVat,
            'vat' => $vatSplit['vat'],
            'vat_exempt' => $vatExempt,
            'non_vat_purchase' => $nonVat,
            'net_purchases' => $vatSplit['net_purchases'],
            'created_by' => $request->user()->id,
        ]);
        $checkVoucher->applyEwt();
        $checkVoucher->save();

        $service->recomputeStatus();
    }

    public function show(Request $request, Service $service)
    {
        $this->authorizeBranchRecord($request, $service->branch_id);
        $service->load(['supplier', 'expenseAccount', 'checkVouchers.checkRegisterEntry', 'attachments']);

        return view('services.show', compact('service'));
    }

    public function edit(Request $request, Service $service)
    {
        $this->authorizeBranchRecord($request, $service->branch_id);

        return view('services.edit', [
            'service' => $service,
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeBranchRecord($request, $service->branch_id);

        if ($service->status !== 'unpaid') {
            throw ValidationException::withMessages([
                'ref_no' => 'This Service has payments recorded against it and can no longer be edited.',
            ]);
        }

        $validated = $this->validateService($request, $service);
        $vatSplit = VatCalculator::split((float) ($validated['amount_w_vat'] ?? 0));

        $service->update([
            ...Arr::except($validated, ['bank_account_id', 'payment_method', 'allow_duplicate_invoice']),
            'vat' => $vatSplit['vat'],
            'net_purchases' => $vatSplit['net_purchases'],
            'vat_exempt' => $validated['vat_exempt'] ?? 0,
            'non_vat_purchase' => $validated['non_vat_purchase'] ?? 0,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('services.index')->with('success', 'Service updated successfully.');
    }

    public function destroy(Request $request, Service $service)
    {
        $this->authorizeBranchRecord($request, $service->branch_id);

        if ($service->status !== 'unpaid') {
            return back()->with('error', 'This Service has payments recorded against it and cannot be deleted.');
        }

        $service->delete();

        return redirect()->route('services.index')->with('success', 'Service deleted successfully.');
    }

    private function validateService(Request $request, ?Service $service = null): array
    {
        $paymentType = $request->input('service_payment_type', 'credit');

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'branch_id' => [
                Rule::requiredIf(fn () => $request->user()->isAdmin() && ! $request->session()->get('selected_branch_id')),
                'nullable', 'exists:branches,id',
            ],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'payor' => ['required', 'string', 'max:255'],
            'expense_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('type', 'debit_expense')],
            'si_no' => ['nullable', 'string', 'max:100'],
            'allow_duplicate_invoice' => ['nullable', 'boolean'],
            'service_payment_type' => ['required', Rule::in(['credit', 'immediate'])],
            'bank_account_id' => [Rule::requiredIf(fn () => $paymentType === 'immediate'), 'nullable', 'exists:bank_accounts,id'],
            'payment_method' => [Rule::requiredIf(fn () => $paymentType === 'immediate'), 'nullable', Rule::in(['cash', 'bank_transfer', 'online'])],
            'amount_w_vat' => ['required', 'numeric', 'min:0'],
            'vat_exempt' => ['nullable', 'numeric', 'min:0'],
            'non_vat_purchase' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $total = (float) ($validated['amount_w_vat'] ?? 0)
            + (float) ($validated['vat_exempt'] ?? 0)
            + (float) ($validated['non_vat_purchase'] ?? 0);

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'amount_w_vat' => 'The Service needs an amount (VAT, VAT-exempt, or non-VAT).',
            ]);
        }

        $this->ensureInvoiceNotDuplicated($validated, $service);

        if (! $request->user()->isAdmin()) {
            unset($validated['branch_id']);
        }

        return $validated;
    }

    private function ensureInvoiceNotDuplicated(array $validated, ?Service $service): void
    {
        if (empty($validated['si_no']) || empty($validated['supplier_id']) || ! empty($validated['allow_duplicate_invoice'])) {
            return;
        }

        $exists = Service::where('supplier_id', $validated['supplier_id'])
            ->where('si_no', $validated['si_no'])
            ->when($service, fn ($q) => $q->whereKeyNot($service->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'si_no' => 'This supplier already has a Service recorded with Invoice # '.$validated['si_no'].'. Check "allow duplicate" if this is intentional.',
            ]);
        }
    }

    private function formOptions(Request $request): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'expenseAccounts' => ChartOfAccount::where('type', 'debit_expense')->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)
                ->when($this->activeBranchId($request), fn ($q, $id) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $id)))
                ->orderBy('bank_name')->get(),
        ];
    }
}
