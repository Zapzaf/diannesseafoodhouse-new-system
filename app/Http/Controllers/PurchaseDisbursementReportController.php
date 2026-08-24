<?php

namespace App\Http\Controllers;

use App\Exports\PurchaseDisbursementSummaryExport;
use App\Models\CheckRegister;
use App\Models\CheckVoucher;
use App\Models\PettyCashVoucher;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherItem;
use App\Models\Service;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseDisbursementReportController extends Controller
{
    public function summary(Request $request): View
    {
        [$apvTotals, $pcvTotals, $cvTotals, $dateFrom, $dateTo] = $this->buildTotals($request);
        $serviceTotals = $this->buildServiceTotals($request, $dateFrom, $dateTo);
        $checkRegisterTotal = $this->buildCheckRegisterTotal($request, $dateFrom, $dateTo);

        $rows = $this->buildDetailedRows($request, $dateFrom, $dateTo);
        $search = trim((string) $request->input('search', ''));

        return view('reports.purchase-disbursement.summary', compact(
            'apvTotals', 'pcvTotals', 'cvTotals', 'serviceTotals', 'checkRegisterTotal',
            'dateFrom', 'dateTo', 'rows', 'search'
        ));
    }

    public function exportSummary(Request $request)
    {
        [, , , $dateFrom, $dateTo] = $this->buildTotals($request);
        $rows = $this->buildDetailedRows($request, $dateFrom, $dateTo);

        $filename = 'purchase-disbursement-summary-'.$dateFrom.'-to-'.$dateTo.'.xlsx';

        return Excel::download(
            new PurchaseDisbursementSummaryExport(
                $rows->where('voucher_type', 'APV')->values(),
                $rows->where('voucher_type', 'CV')->values(),
                $rows->where('voucher_type', 'PCV')->values(),
                $rows->where('voucher_type', 'Check Register')->values(),
                $dateFrom,
                $dateTo
            ),
            $filename
        );
    }

    private function buildTotals(Request $request): array
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        $apvTotals = PurchaseVoucherItem::query()
            ->whereHas('purchaseVoucher', fn ($q) => $q->whereBetween('date', [$dateFrom, $dateTo])
                ->when($branchId, fn ($inner, $id) => $inner->where('branch_id', $id))
                ->when($search, fn ($inner, $s) => $inner->where(fn ($w) => $w->where('apv_no', 'like', "%{$s}%")
                    ->orWhere('buyer', 'like', "%{$s}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%")))))
            ->selectRaw('COALESCE(SUM(net_purchases),0) as net_purchases, COALESCE(SUM(vat),0) as vat, COALESCE(SUM(vat_exempt),0) as vat_exempt, COALESCE(SUM(non_vat_purchase),0) as non_vat_purchase, COALESCE(SUM(total_purchases),0) as total_purchases')
            ->first();

        $pcvTotals = PettyCashVoucher::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('pcv_no', 'like', "%{$s}%")
                ->orWhere('remarks', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($v) => $v->where('name', 'like', "%{$s}%"))))
            ->with('items')
            ->get()
            ->reduce(function (array $carry, PettyCashVoucher $pcv) {
                foreach ($pcv->items as $item) {
                    $carry['net_purchases'] += (float) $item->net_purchases;
                    $carry['vat'] += (float) $item->vat;
                    $carry['vat_exempt'] += (float) $item->vat_exempt;
                    $carry['non_vat_purchase'] += (float) $item->non_vat_purchase;
                }

                return $carry;
            }, ['net_purchases' => 0, 'vat' => 0, 'vat_exempt' => 0, 'non_vat_purchase' => 0]);
        $pcvTotals['total_purchases'] = $pcvTotals['net_purchases'] + $pcvTotals['vat'] + $pcvTotals['vat_exempt'] + $pcvTotals['non_vat_purchase'];

        $cvTotals = CheckVoucher::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('cv_no', 'like', "%{$s}%")
                ->orWhere('payee_name', 'like', "%{$s}%")
                ->orWhere('particulars', 'like', "%{$s}%")
                ->orWhere('tin', 'like', "%{$s}%")))
            ->selectRaw('COALESCE(SUM(net_purchases),0) as net_purchases, COALESCE(SUM(vat),0) as vat, COALESCE(SUM(vat_exempt),0) as vat_exempt, COALESCE(SUM(non_vat_purchase),0) as non_vat_purchase, COALESCE(SUM(amount_paid),0) as amount_paid, COALESCE(SUM(ewt_amount),0) as ewt_amount')
            ->first();

        return [$apvTotals, $pcvTotals, $cvTotals, $dateFrom, $dateTo];
    }

    private function buildServiceTotals(Request $request, string $dateFrom, string $dateTo): object
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return Service::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('ref_no', 'like', "%{$s}%")
                ->orWhere('payor', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($v) => $v->where('name', 'like', "%{$s}%"))))
            ->selectRaw('COALESCE(SUM(net_purchases),0) as net_purchases, COALESCE(SUM(vat),0) as vat, COALESCE(SUM(vat_exempt),0) as vat_exempt, COALESCE(SUM(non_vat_purchase),0) as non_vat_purchase, COALESCE(SUM(total_purchases),0) as total_purchases')
            ->first();
    }

    private function buildCheckRegisterTotal(Request $request, string $dateFrom, string $dateTo): float
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return (float) CheckRegister::query()
            ->whereBetween('check_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('check_no', 'like', "%{$s}%")
                ->orWhere('payee', 'like', "%{$s}%")
                ->orWhere('particulars', 'like', "%{$s}%")))
            ->sum('amount');
    }

    /**
     * Unified, row-level listing across every disbursement source (APV, PCV,
     * CV, Check Register) for the "detailed" report table and its matching
     * Excel export — the client explicitly didn't want this report to stay
     * a single totals row per ledger; every actual voucher shows here, each
     * tagged with its own Voucher Type, sorted newest first.
     */
    private function buildDetailedRows(Request $request, string $dateFrom, string $dateTo): Collection
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        $apv = PurchaseVoucher::with(['vendor', 'branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('apv_no', 'like', "%{$s}%")
                ->orWhere('buyer', 'like', "%{$s}%")
                ->orWhere('si_no', 'like', "%{$s}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%"))))
            ->get()
            ->map(fn (PurchaseVoucher $v) => (object) [
                'voucher_no' => $v->apv_no,
                'voucher_type' => 'APV',
                'date' => $v->date,
                'payee' => $v->vendor?->name ?? $v->buyer ?? '—',
                'tin' => $v->vendor?->tin,
                'particulars' => trim(collect(['Buyer: '.$v->buyer, $v->si_no ? 'SI#: '.$v->si_no : null])->filter()->implode(' | ')) ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->status,
                'amount' => (float) $v->payable_total,
            ]);

        $pcv = PettyCashVoucher::with(['supplier', 'branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('pcv_no', 'like', "%{$s}%")
                ->orWhere('remarks', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($v) => $v->where('name', 'like', "%{$s}%"))))
            ->get()
            ->map(fn (PettyCashVoucher $v) => (object) [
                'voucher_no' => $v->pcv_no,
                'voucher_type' => 'PCV',
                'date' => $v->date,
                'payee' => $v->supplier?->name ?? '—',
                'tin' => $v->supplier?->tin,
                'particulars' => $v->remarks ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->check_voucher_id ? 'Replenished' : 'Pending Replenishment',
                'amount' => (float) $v->total,
            ]);

        $cv = CheckVoucher::with(['supplier', 'branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('cv_no', 'like', "%{$s}%")
                ->orWhere('payee_name', 'like', "%{$s}%")
                ->orWhere('particulars', 'like', "%{$s}%")
                ->orWhere('tin', 'like', "%{$s}%")))
            ->get()
            ->map(fn (CheckVoucher $v) => (object) [
                'voucher_no' => $v->cv_no,
                'voucher_type' => 'CV',
                'date' => $v->date,
                'payee' => $v->payee_name ?: ($v->supplier?->name ?? '—'),
                'tin' => $v->tin ?: $v->supplier?->tin,
                'particulars' => $v->particulars ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->status,
                'amount' => (float) $v->amount_paid,
            ]);

        $checkRegister = CheckRegister::with(['checkVoucher.supplier', 'branch'])
            ->whereBetween('check_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('check_no', 'like', "%{$s}%")
                ->orWhere('payee', 'like', "%{$s}%")
                ->orWhere('particulars', 'like', "%{$s}%")))
            ->get()
            ->map(fn (CheckRegister $v) => (object) [
                'voucher_no' => $v->check_no,
                'voucher_type' => 'Check Register',
                'date' => $v->check_date,
                'payee' => $v->payee ?: '—',
                'tin' => $v->checkVoucher?->tin ?: $v->checkVoucher?->supplier?->tin,
                'particulars' => $v->particulars ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->status,
                'amount' => (float) $v->amount,
            ]);

        return $apv->concat($pcv)->concat($cv)->concat($checkRegister)
            ->sortByDesc(fn ($row) => $row->date)
            ->values();
    }

    public function unpaidApvAging(Request $request): View
    {
        $vouchers = PurchaseVoucher::with(['vendor', 'items'])
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->orderBy('date')
            ->get()
            ->map(function (PurchaseVoucher $voucher) {
                $daysOutstanding = $voucher->date->diffInDays(now());

                return (object) [
                    'voucher' => $voucher,
                    'days_outstanding' => $daysOutstanding,
                    'bucket' => match (true) {
                        $daysOutstanding <= 30 => '0-30 days',
                        $daysOutstanding <= 60 => '31-60 days',
                        $daysOutstanding <= 90 => '61-90 days',
                        default => 'Over 90 days',
                    },
                    'remaining_balance' => round($voucher->payable_total - $voucher->amount_paid, 2),
                ];
            });

        $totalOutstanding = $vouchers->sum('remaining_balance');

        return view('reports.purchase-disbursement.aging', compact('vouchers', 'totalOutstanding'));
    }

    public function pettyCashFund(Request $request): View
    {
        $vouchers = PettyCashVoucher::with(['items', 'checkVoucher'])
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('date')
            ->get();

        $totalSpent = $vouchers->sum('total');
        $totalReplenished = $vouchers->filter->isReplenished()->sum('total');
        $pendingReplenishment = $totalSpent - $totalReplenished;

        return view('reports.purchase-disbursement.petty-cash-fund', compact('vouchers', 'totalSpent', 'totalReplenished', 'pendingReplenishment'));
    }

    /**
     * Unified Outstanding Payables view: unpaid/partially-paid Purchases (APV) and
     * Services in one list, filterable by supplier/buyer-payor/date/status.
     */
    public function payables(Request $request): View
    {
        $branchId = $this->activeBranchId($request);
        $supplierId = $request->input('supplier_id');
        $party = trim((string) $request->input('party', ''));
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $purchases = PurchaseVoucher::with(['vendor', 'items'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($supplierId, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($party, fn ($q, $p) => $q->where('buyer', 'like', "%{$p}%"))
            ->when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['unpaid', 'partially_paid']))
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->get()
            ->map(fn (PurchaseVoucher $voucher) => (object) [
                'module' => 'Purchase',
                'ref_no' => $voucher->apv_no,
                'si_no' => $voucher->si_no,
                'supplier' => $voucher->vendor?->name,
                'party' => $voucher->buyer,
                'date' => $voucher->date,
                'original_amount' => $voucher->payable_total,
                'amount_paid' => $voucher->amount_paid,
                'remaining_balance' => round($voucher->payable_total - $voucher->amount_paid, 2),
                'status' => $voucher->status,
                'record' => $voucher,
            ]);

        $services = Service::with(['supplier'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($supplierId, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($party, fn ($q, $p) => $q->where('payor', 'like', "%{$p}%"))
            ->when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['unpaid', 'partially_paid']))
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->get()
            ->map(fn (Service $service) => (object) [
                'module' => 'Service',
                'ref_no' => $service->ref_no,
                'si_no' => $service->si_no,
                'supplier' => $service->supplier?->name,
                'party' => $service->payor,
                'date' => $service->date,
                'original_amount' => $service->payable_total,
                'amount_paid' => $service->amount_paid,
                'remaining_balance' => round($service->payable_total - $service->amount_paid, 2),
                'status' => $service->status,
                'record' => $service,
            ]);

        $payables = $purchases->concat($services)->sortBy('date')->values();
        $totalOutstanding = $payables->sum('remaining_balance');

        return view('reports.payables.index', compact('payables', 'totalOutstanding'));
    }

    public function payablesPdf(Request $request): Response
    {
        $view = $this->payables($request);
        $html = view('reports.payables.pdf', $view->getData())->render();

        return $this->streamPdf($html, 'outstanding-payables-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Advance disbursements (type = advance) with outstanding vs. liquidated amounts.
     */
    public function advances(Request $request): View
    {
        $advances = CheckVoucher::with(['advanceAccount', 'supplier', 'liquidations'])
            ->where('type', 'advance')
            ->when($this->activeBranchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->orderByDesc('date')
            ->get();

        $totalOutstanding = $advances->sum('outstanding_advance');

        return view('reports.payables.advances', compact('advances', 'totalOutstanding'));
    }

    private function streamPdf(string $html, string $filename): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
