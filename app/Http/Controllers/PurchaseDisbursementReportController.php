<?php

namespace App\Http\Controllers;

use App\Exports\PurchaseDisbursementSummaryExport;
use App\Models\CheckRegister;
use App\Models\CheckVoucher;
use App\Models\PettyCashVoucher;
use App\Models\PettyCashVoucherItem;
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

        // Row counts/grand total for the header cards — the tables themselves
        // are populated client-side (each paginated independently), so this
        // is the one place that still needs every matching row up front.
        $rowCounts = $this->buildDetailedRows($request, $dateFrom, $dateTo);
        $search = trim((string) $request->input('search', ''));

        return view('reports.purchase-disbursement.summary', compact(
            'apvTotals', 'pcvTotals', 'cvTotals', 'serviceTotals', 'checkRegisterTotal',
            'dateFrom', 'dateTo', 'rowCounts', 'search'
        ));
    }

    /**
     * The Excel export is a proper itemized accounting ledger — one row per
     * line item/cost allocation rather than one row per voucher — matching
     * the client's own manual bookkeeping spreadsheet layout (APV/PCV items
     * broken out by quantity+cost account, CV disbursements broken out by
     * each receipt's own cost account, plus the APV#/EWT/credit-account
     * columns their accountant already works with).
     */
    public function exportSummary(Request $request)
    {
        [, , , $dateFrom, $dateTo] = $this->buildTotals($request);

        $sheets = [
            $this->buildApvLedgerSheet($request, $dateFrom, $dateTo),
            $this->buildCvLedgerSheet($request, $dateFrom, $dateTo),
            $this->buildPcvLedgerSheet($request, $dateFrom, $dateTo),
            $this->buildCheckRegisterLedgerSheet($request, $dateFrom, $dateTo),
        ];

        $filename = 'purchase-disbursement-summary-'.$dateFrom.'-to-'.$dateTo.'.xlsx';

        return Excel::download(new PurchaseDisbursementSummaryExport($sheets, $dateFrom, $dateTo), $filename);
    }

    private const CURRENCY_FORMAT = '"₱"#,##0.00';

    /**
     * Blanks the given columns on every row after the first in a run of
     * consecutive rows sharing the same group key — matches the client's own
     * ledger format, where the Date/Voucher # is only written once per
     * voucher (its first line item), not repeated on every line beneath it.
     * Rows must already be ordered by voucher so each voucher's lines are
     * consecutive.
     *
     * @param array<int, array> $rows
     * @param array<int, mixed> $groupKeys One key per row, e.g. the underlying model's id — not necessarily a displayed column, since something like cv_no isn't guaranteed unique across disbursement types.
     * @param array<int, int> $blankColumnIndexes
     */
    private function blankRepeatedGroupColumns(array $rows, array $groupKeys, array $blankColumnIndexes): array
    {
        $previous = null;

        foreach ($rows as $i => &$row) {
            $current = $groupKeys[$i];

            if ($previous !== null && $current === $previous) {
                foreach ($blankColumnIndexes as $index) {
                    $row[$index] = '';
                }
            }

            $previous = $current;
        }

        return $rows;
    }

    private function buildApvLedgerSheet(Request $request, string $dateFrom, string $dateTo): array
    {
        $items = $this->apvItemsQuery($request, $dateFrom, $dateTo)->get();

        $rows = $items->map(function (PurchaseVoucherItem $item) {
            $apv = $item->purchaseVoucher;

            return [
                $apv->date?->format('Y-m-d') ?? '',
                $apv->apv_no ?? '—',
                (float) $item->quantity,
                $item->unit ?? '—',
                $item->particulars ?? '—',
                $item->costAccount?->name ?? '—',
                $apv->creditAccount?->name ?? '—',
                $apv->vendor?->name ?? '—',
                $apv->vendor?->address ?? '—',
                $apv->si_no ?? '—',
                $apv->vendor?->tin ?? '—',
                (float) $item->amount_w_vat,
                (float) $item->vat,
                (float) $item->net_purchases,
                (float) $item->vat_exempt,
                (float) $item->non_vat_purchase,
                (float) $item->payable_amount,
                $apv->branch?->name ?? '—',
            ];
        })->all();

        // Date/APV # shown once per voucher, blank on the rest of its lines.
        $rows = $this->blankRepeatedGroupColumns($rows, $items->pluck('purchase_voucher_id')->all(), [0, 1]);

        return [
            'title' => 'APV',
            'headings' => [
                'Date', 'APV #', 'Quantity', 'Unit', 'Particulars',
                'Cost/Expense Account', 'Credit Account', "Vendor's Name", 'Address',
                'SI/No.', 'TIN', 'Amount w/ VAT', 'VAT', 'Net Purchases',
                'VAT Exempt', 'Non-VAT Purchase', 'Total Purchases', 'Branch',
            ],
            'rows' => $rows,
            'numericFormats' => [
                'C' => '#,##0.00',
                'L' => self::CURRENCY_FORMAT,
                'M' => self::CURRENCY_FORMAT,
                'N' => self::CURRENCY_FORMAT,
                'O' => self::CURRENCY_FORMAT,
                'P' => self::CURRENCY_FORMAT,
                'Q' => self::CURRENCY_FORMAT,
            ],
            'totals' => ['Q' => 'Total Purchases'],
        ];
    }

    private function buildPcvLedgerSheet(Request $request, string $dateFrom, string $dateTo): array
    {
        $items = $this->pcvItemsQuery($request, $dateFrom, $dateTo)->get();

        $rows = $items->map(function (PettyCashVoucherItem $item) {
            $pcv = $item->pettyCashVoucher;

            return [
                $pcv->date?->format('Y-m-d') ?? '',
                $pcv->pcv_no ?? '—',
                (float) $item->quantity,
                $item->unit ?? '—',
                $item->particulars ?? '—',
                $item->costAccount?->name ?? '—',
                $pcv->supplier?->name ?? '—',
                $pcv->supplier?->tin ?? '—',
                (float) $item->amount_w_vat,
                (float) $item->vat,
                (float) $item->net_purchases,
                (float) $item->vat_exempt,
                (float) $item->non_vat_purchase,
                (float) $item->total_purchases,
                $pcv->checkVoucher?->cv_no ?: 'Not yet replenished',
                $pcv->branch?->name ?? '—',
            ];
        })->all();

        // Date/PCV # shown once per voucher, blank on the rest of its lines.
        $rows = $this->blankRepeatedGroupColumns($rows, $items->pluck('petty_cash_voucher_id')->all(), [0, 1]);

        return [
            'title' => 'PCV',
            'headings' => [
                'Date', 'PCV #', 'Quantity', 'Unit', 'Particulars',
                'Cost/Expense Account', "Payee's Name", 'TIN', 'Amount w/ VAT', 'VAT',
                'Net Purchases', 'VAT Exempt', 'Non-VAT Purchase', 'Total Purchases',
                'Replenished By (CV #)', 'Branch',
            ],
            'rows' => $rows,
            'numericFormats' => [
                'C' => '#,##0.00',
                'I' => self::CURRENCY_FORMAT,
                'J' => self::CURRENCY_FORMAT,
                'K' => self::CURRENCY_FORMAT,
                'L' => self::CURRENCY_FORMAT,
                'M' => self::CURRENCY_FORMAT,
                'N' => self::CURRENCY_FORMAT,
            ],
            'totals' => ['N' => 'Total Purchases'],
        ];
    }

    private function buildCvLedgerSheet(Request $request, string $dateFrom, string $dateTo): array
    {
        $vouchers = $this->cvDetailQuery($request, $dateFrom, $dateTo)
            ->with(['receipts.costAccount', 'receipts.supplier', 'costAccount', 'advanceAccount', 'purchaseVoucher'])
            ->get();

        $rows = [];
        $groupKeys = [];

        foreach ($vouchers as $cv) {
            $apvNo = $cv->purchaseVoucher?->apv_no ?? '—';
            $branchName = $cv->branch?->name ?? '—';
            $fallbackPayee = $cv->payee_name ?: ($cv->supplier?->name ?? '—');
            $fallbackAddress = $cv->address ?: ($cv->supplier?->address ?? '—');
            $fallbackTin = $cv->tin ?: ($cv->supplier?->tin ?? '—');

            // Standalone CVs (COD/Other) can split across several cost
            // accounts and suppliers — one row per receipt, each with its
            // own payee, so a single check covering several different
            // suppliers still shows who each portion was actually for.
            if ($cv->receipts->isNotEmpty()) {
                foreach ($cv->receipts as $receipt) {
                    $rows[] = [
                        $cv->date?->format('Y-m-d') ?? '',
                        $cv->cv_no ?? '—',
                        $apvNo,
                        $cv->particulars ?: '—',
                        $receipt->costAccount?->name ?? '—',
                        $receipt->supplier?->name ?? $fallbackPayee,
                        $receipt->supplier?->address ?? $fallbackAddress,
                        $receipt->si_no ?: '—',
                        $receipt->supplier?->tin ?? $fallbackTin,
                        (float) $receipt->amount_w_vat,
                        (float) $receipt->vat,
                        (float) $receipt->net_purchases,
                        (float) $receipt->vat_exempt,
                        (float) $receipt->non_vat_purchase,
                        (float) $cv->ewt_rate * 100,
                        (float) $cv->ewt_amount,
                        $branchName,
                    ];
                    $groupKeys[] = $cv->id;
                }

                continue;
            }

            $rows[] = [
                $cv->date?->format('Y-m-d') ?? '',
                $cv->cv_no ?? '—',
                $apvNo,
                $cv->particulars ?: '—',
                $cv->costAccount?->name ?? $cv->advanceAccount?->name ?? '—',
                $fallbackPayee,
                $fallbackAddress,
                $cv->si_no ?: '—',
                $fallbackTin,
                (float) $cv->amount_w_vat,
                (float) $cv->vat,
                (float) $cv->net_purchases,
                (float) $cv->vat_exempt,
                (float) $cv->non_vat_purchase,
                (float) $cv->ewt_rate * 100,
                (float) $cv->ewt_amount,
                $branchName,
            ];
            $groupKeys[] = $cv->id;
        }

        // Date/CV #/APV #/EWT shown once per voucher, blank on the rest of
        // its lines — cv_no alone isn't a safe group key (it's only unique
        // within its own disbursement type), so this groups by the CV's
        // actual id instead.
        $rows = $this->blankRepeatedGroupColumns($rows, $groupKeys, [0, 1, 2, 14, 15]);

        return [
            'title' => 'CV',
            'headings' => [
                'Date', 'CV #', 'APV #', 'Particulars', 'Cost/Expense Account (Debit)',
                "Payee's Name", 'Address', 'SI/No.', 'TIN', 'Amount w/ VAT', 'VAT',
                'Net Purchases', 'VAT Exempt', 'Non-VAT Purchase', 'EWT Rate (%)',
                'EWT Amount', 'Branch',
            ],
            'rows' => $rows,
            'numericFormats' => [
                'J' => self::CURRENCY_FORMAT,
                'K' => self::CURRENCY_FORMAT,
                'L' => self::CURRENCY_FORMAT,
                'M' => self::CURRENCY_FORMAT,
                'N' => self::CURRENCY_FORMAT,
                'O' => '#,##0.00',
                'P' => self::CURRENCY_FORMAT,
            ],
            'totals' => ['J' => 'Amount w/ VAT', 'P' => 'EWT Amount'],
        ];
    }

    private function buildCheckRegisterLedgerSheet(Request $request, string $dateFrom, string $dateTo): array
    {
        $checks = $this->checkRegisterDetailQuery($request, $dateFrom, $dateTo)->get();

        $rows = $checks->map(fn (CheckRegister $check) => [
            $check->check_date?->format('Y-m-d') ?? '',
            $check->checkVoucher?->cv_no ?? '—',
            $check->check_no ?? '—',
            $check->payee ?: '—',
            $check->particulars ?: '—',
            (float) $check->amount,
            $check->branch?->name ?? '—',
        ])->all();

        return [
            'title' => 'Check Register',
            'headings' => ['Check Date', 'CV #', 'Check #', 'Payee', 'Particulars', 'Amount', 'Branch'],
            'rows' => $rows,
            'numericFormats' => ['F' => self::CURRENCY_FORMAT],
            'totals' => ['F' => 'Amount'],
        ];
    }

    private function apvItemsQuery(Request $request, string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return PurchaseVoucherItem::with(['purchaseVoucher.vendor', 'purchaseVoucher.branch', 'purchaseVoucher.creditAccount', 'costAccount'])
            ->whereHas('purchaseVoucher', fn ($q) => $q->whereBetween('date', [$dateFrom, $dateTo])
                ->when($branchId, fn ($inner, $id) => $inner->where('branch_id', $id))
                ->when($search, fn ($inner, $s) => $inner->where(fn ($w) => $w->where('apv_no', 'like', "%{$s}%")
                    ->orWhere('buyer', 'like', "%{$s}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%")))))
            ->orderBy('purchase_voucher_id')
            ->orderBy('id');
    }

    private function pcvItemsQuery(Request $request, string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return PettyCashVoucherItem::with(['pettyCashVoucher.supplier', 'pettyCashVoucher.branch', 'pettyCashVoucher.checkVoucher', 'costAccount'])
            ->whereHas('pettyCashVoucher', fn ($q) => $q->whereBetween('date', [$dateFrom, $dateTo])
                ->when($branchId, fn ($inner, $id) => $inner->where('branch_id', $id))
                ->when($search, fn ($inner, $s) => $inner->where(fn ($w) => $w->where('pcv_no', 'like', "%{$s}%")
                    ->orWhere('remarks', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($v) => $v->where('name', 'like', "%{$s}%")))))
            ->orderBy('petty_cash_voucher_id')
            ->orderBy('id');
    }

    /**
     * Paginated JSON feeds for the four "Detailed Disbursements" tables —
     * same IndexTableBridge AJAX pattern (and the same per-table
     * tbodyId/paginationId/infoId/stateKey override mechanism) already used
     * on the Costing Report, so each table paginates independently instead
     * of every row for every voucher type loading into the page at once.
     */
    public function summaryApvData(Request $request): \Illuminate\Http\JsonResponse
    {
        [, , , $dateFrom, $dateTo] = $this->buildTotals($request);

        $vouchers = $this->apvDetailQuery($request, $dateFrom, $dateTo)
            ->latest('date')
            ->paginate($this->perPage($request, 15))
            ->through(fn (PurchaseVoucher $v) => $this->mapDetailRowForJson('APV', $v));

        return response()->json($vouchers);
    }

    public function summaryCvData(Request $request): \Illuminate\Http\JsonResponse
    {
        [, , , $dateFrom, $dateTo] = $this->buildTotals($request);

        $vouchers = $this->cvDetailQuery($request, $dateFrom, $dateTo)
            ->latest('date')
            ->paginate($this->perPage($request, 15))
            ->through(fn (CheckVoucher $v) => $this->mapDetailRowForJson('CV', $v));

        return response()->json($vouchers);
    }

    public function summaryPcvData(Request $request): \Illuminate\Http\JsonResponse
    {
        [, , , $dateFrom, $dateTo] = $this->buildTotals($request);

        $vouchers = $this->pcvDetailQuery($request, $dateFrom, $dateTo)
            ->latest('date')
            ->paginate($this->perPage($request, 15))
            ->through(fn (PettyCashVoucher $v) => $this->mapDetailRowForJson('PCV', $v));

        return response()->json($vouchers);
    }

    public function summaryCheckRegisterData(Request $request): \Illuminate\Http\JsonResponse
    {
        [, , , $dateFrom, $dateTo] = $this->buildTotals($request);

        $checks = $this->checkRegisterDetailQuery($request, $dateFrom, $dateTo)
            ->latest('check_date')
            ->paginate($this->perPage($request, 15))
            ->through(fn (CheckRegister $v) => $this->mapDetailRowForJson('Check Register', $v));

        return response()->json($checks);
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

    private function apvDetailQuery(Request $request, string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return PurchaseVoucher::with(['vendor', 'branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('apv_no', 'like', "%{$s}%")
                ->orWhere('buyer', 'like', "%{$s}%")
                ->orWhere('si_no', 'like', "%{$s}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%"))));
    }

    private function pcvDetailQuery(Request $request, string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return PettyCashVoucher::with(['supplier', 'branch', 'checkVoucher'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('pcv_no', 'like', "%{$s}%")
                ->orWhere('remarks', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($v) => $v->where('name', 'like', "%{$s}%"))));
    }

    private function cvDetailQuery(Request $request, string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return CheckVoucher::with(['supplier', 'branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('cv_no', 'like', "%{$s}%")
                ->orWhere('payee_name', 'like', "%{$s}%")
                ->orWhere('particulars', 'like', "%{$s}%")
                ->orWhere('tin', 'like', "%{$s}%")));
    }

    private function checkRegisterDetailQuery(Request $request, string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $branchId = $this->activeBranchId($request);
        $search = trim((string) $request->input('search', ''));

        return CheckRegister::with(['checkVoucher.supplier', 'branch'])
            ->whereBetween('check_date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('check_no', 'like', "%{$s}%")
                ->orWhere('payee', 'like', "%{$s}%")
                ->orWhere('particulars', 'like', "%{$s}%")));
    }

    /**
     * Same as mapDetailRow(), for the JSON pagination feeds specifically —
     * formats the date to a plain display string server-side rather than
     * shipping the raw Carbon instance, since JSON-encoding a Carbon date
     * produces a full ISO datetime (e.g. "2026-08-01T00:00:00.000000Z")
     * that would misleadingly look like it carries a time component.
     */
    private function mapDetailRowForJson(string $type, PurchaseVoucher|PettyCashVoucher|CheckVoucher|CheckRegister $v): object
    {
        $row = $this->mapDetailRow($type, $v);
        $row->date = $row->date?->format('M d, Y');

        return $row;
    }

    /**
     * Maps one voucher/check-register model to the unified row shape used by
     * the detailed table, the JSON pagination feeds, and the Excel export —
     * one place so all three can never drift on what a "row" looks like.
     */
    private function mapDetailRow(string $type, PurchaseVoucher|PettyCashVoucher|CheckVoucher|CheckRegister $v): object
    {
        return match ($type) {
            'APV' => (object) [
                'voucher_no' => $v->apv_no,
                'voucher_type' => 'APV',
                'date' => $v->date,
                'payee' => $v->vendor?->name ?? $v->buyer ?? '—',
                'tin' => $v->vendor?->tin,
                'particulars' => trim(collect(['Buyer: '.$v->buyer, $v->si_no ? 'SI#: '.$v->si_no : null])->filter()->implode(' | ')) ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->status,
                'linked_voucher' => '—',
                'amount' => (float) $v->payable_total,
            ],
            'PCV' => (object) [
                'voucher_no' => $v->pcv_no,
                'voucher_type' => 'PCV',
                'date' => $v->date,
                'payee' => $v->supplier?->name ?? '—',
                'tin' => $v->supplier?->tin,
                'particulars' => $v->remarks ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->check_voucher_id ? 'Replenished' : 'Pending Replenishment',
                // Which CV actually reimbursed this PCV — the client wanted this
                // cross-reference visible instead of having to look it up separately.
                'linked_voucher' => $v->checkVoucher?->cv_no ?: 'Not yet replenished',
                'amount' => (float) $v->total,
            ],
            'CV' => (object) [
                'voucher_no' => $v->cv_no,
                'voucher_type' => 'CV',
                'date' => $v->date,
                'payee' => $v->payee_name ?: ($v->supplier?->name ?? '—'),
                'tin' => $v->tin ?: $v->supplier?->tin,
                'particulars' => $v->particulars ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->status,
                'linked_voucher' => '—',
                'amount' => (float) $v->amount_paid,
            ],
            'Check Register' => (object) [
                'voucher_no' => $v->check_no,
                'voucher_type' => 'Check Register',
                'date' => $v->check_date,
                'payee' => $v->payee ?: '—',
                'tin' => $v->checkVoucher?->tin ?: $v->checkVoucher?->supplier?->tin,
                'particulars' => $v->particulars ?: '—',
                'branch' => $v->branch?->name,
                'status' => $v->status,
                'linked_voucher' => '—',
                'amount' => (float) $v->amount,
            ],
        };
    }

    /**
     * Unified, row-level listing across every disbursement source (APV, PCV,
     * CV, Check Register) — used for the Excel export (which needs every
     * matching row, not just one page) and for the header cards' counts and
     * grand total on the report page. The four on-page tables themselves
     * are paginated independently via summaryApvData()/summaryCvData()/etc.
     */
    private function buildDetailedRows(Request $request, string $dateFrom, string $dateTo): Collection
    {
        $apv = $this->apvDetailQuery($request, $dateFrom, $dateTo)->get()->map(fn ($v) => $this->mapDetailRow('APV', $v));
        $pcv = $this->pcvDetailQuery($request, $dateFrom, $dateTo)->get()->map(fn ($v) => $this->mapDetailRow('PCV', $v));
        $cv = $this->cvDetailQuery($request, $dateFrom, $dateTo)->get()->map(fn ($v) => $this->mapDetailRow('CV', $v));
        $checkRegister = $this->checkRegisterDetailQuery($request, $dateFrom, $dateTo)->get()->map(fn ($v) => $this->mapDetailRow('Check Register', $v));

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
