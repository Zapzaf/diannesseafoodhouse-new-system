<?php

namespace App\Http\Controllers;

use App\Models\CheckVoucher;
use App\Models\PettyCashVoucher;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseVoucherItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseDisbursementReportController extends Controller
{
    public function summary(Request $request): View
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $branchId = $this->activeBranchId($request);

        $apvTotals = PurchaseVoucherItem::query()
            ->whereHas('purchaseVoucher', fn ($q) => $q->whereBetween('date', [$dateFrom, $dateTo])
                ->when($branchId, fn ($inner, $id) => $inner->where('branch_id', $id)))
            ->selectRaw('COALESCE(SUM(net_purchases),0) as net_purchases, COALESCE(SUM(vat),0) as vat, COALESCE(SUM(vat_exempt),0) as vat_exempt, COALESCE(SUM(non_vat_purchase),0) as non_vat_purchase, COALESCE(SUM(total_purchases),0) as total_purchases')
            ->first();

        $pcvTotals = PettyCashVoucher::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
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
        $pcvTotals['total_purchases'] = $pcvTotals['net_purchases'] + $pcvTotals['vat_exempt'] + $pcvTotals['non_vat_purchase'];

        $cvTotals = CheckVoucher::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('COALESCE(SUM(net_purchases),0) as net_purchases, COALESCE(SUM(vat),0) as vat, COALESCE(SUM(vat_exempt),0) as vat_exempt, COALESCE(SUM(non_vat_purchase),0) as non_vat_purchase, COALESCE(SUM(amount_paid),0) as amount_paid, COALESCE(SUM(ewt_amount),0) as ewt_amount')
            ->first();

        return view('reports.purchase-disbursement.summary', compact('apvTotals', 'pcvTotals', 'cvTotals', 'dateFrom', 'dateTo'));
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
}
