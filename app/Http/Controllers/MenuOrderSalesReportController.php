<?php

namespace App\Http\Controllers;

use App\Exports\MenuOrderSalesReportExport;
use App\Models\Branch;
use App\Models\MenuOrder;
use App\Models\MenuOrderPayment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MenuOrderSalesReportController extends Controller
{
    public const PAYMENT_METHODS = ['cash', 'gcash', 'card', 'bank'];

    public function index(Request $request): View
    {
        $branchId = $this->activeBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $method = $request->validate(['payment_method' => ['nullable', 'in:' . implode(',', self::PAYMENT_METHODS)]])['payment_method'] ?? '';

        $summary = $this->buildSummary($branchId, $dateFrom, $dateTo, $method);

        $payments = $this->paymentsQuery($branchId, $dateFrom, $dateTo, $method)
            ->with(['order:id,order_number,customer_name,total_pax,status', 'branch', 'receivedBy'])
            ->orderByDesc('payment_date')
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return view('reports.menu-order-sales', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'method' => $method,
            'paymentMethods' => self::PAYMENT_METHODS,
            'payments' => $payments,
            'summary' => $summary,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $branchId = $this->activeBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $method = $request->validate(['payment_method' => ['nullable', 'in:' . implode(',', self::PAYMENT_METHODS)]])['payment_method'] ?? '';

        $summary = $this->buildSummary($branchId, $dateFrom, $dateTo, $method);

        $payments = $this->paymentsQuery($branchId, $dateFrom, $dateTo, $method)
            ->with(['order:id,order_number,customer_name', 'branch', 'receivedBy'])
            ->orderByDesc('payment_date')
            ->latest()
            ->get();

        $filename = 'menu-order-sales-report-' . $dateFrom . '-to-' . $dateTo . '.xlsx';

        return Excel::download(new MenuOrderSalesReportExport($payments, $summary), $filename);
    }

    public function exportPdf(Request $request): Response
    {
        $branchId = $this->activeBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $method = $request->validate(['payment_method' => ['nullable', 'in:' . implode(',', self::PAYMENT_METHODS)]])['payment_method'] ?? '';

        $summary = $this->buildSummary($branchId, $dateFrom, $dateTo, $method);

        $payments = $this->paymentsQuery($branchId, $dateFrom, $dateTo, $method)
            ->with(['order:id,order_number,customer_name', 'branch', 'receivedBy'])
            ->orderByDesc('payment_date')
            ->latest()
            ->get();

        $branchName = $branchId
            ? (Branch::find($branchId)?->name ?? 'Unknown Branch')
            : 'All Branches';

        $html = view('reports.menu-order-sales-pdf', [
            'summary' => $summary,
            'payments' => $payments,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'branchName' => $branchName,
            'method' => $method,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $filename = 'menu-order-sales-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array{0: string, 1: string} [date_from, date_to]
     */
    private function validatedDateRange(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return [
            $validated['date_from'] ?? now()->toDateString(),
            $validated['date_to'] ?? now()->toDateString(),
        ];
    }

    private function paymentsQuery(?int $branchId, string $dateFrom, string $dateTo, string $method): Builder
    {
        return MenuOrderPayment::query()
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($method, fn (Builder $q, string $m) => $q->where('method', $m))
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(?int $branchId, string $dateFrom, string $dateTo, string $method): array
    {
        $base = fn (): Builder => $this->paymentsQuery($branchId, $dateFrom, $dateTo, $method);

        $grossSales = (float) (clone $base())->sum('subtotal') + (float) (clone $base())->sum('additional_charge_amount');
        $totalDiscount = (float) (clone $base())->sum('discount_amount');
        $vatExemptSales = (float) (clone $base())->sum('total_vat_exempt');
        $vatAmount = (float) (clone $base())->sum('vat_amount');
        $netOfDiscount = $grossSales - $totalDiscount;
        $netSales = $netOfDiscount - $vatAmount;
        $vatableSales = max(0, $netSales - $vatExemptSales);
        $amountCollected = (float) (clone $base())->sum('amount');

        $transactionCount = (clone $base())->count();

        $seniorCount = (clone $base())->where('discount_type', 'senior')->count();
        $pwdCount = (clone $base())->where('discount_type', 'pwd')->count();
        $seniorDiscountAmount = (float) (clone $base())->where('discount_type', 'senior')->sum('discount_amount');
        $pwdDiscountAmount = (float) (clone $base())->where('discount_type', 'pwd')->sum('discount_amount');

        $orderIds = (clone $base())->pluck('menu_order_id')->unique()->values();
        $customersServed = (int) MenuOrder::query()->whereIn('id', $orderIds)->sum('total_pax');

        $salesByBranch = (clone $base())
            ->selectRaw('branch_id, COUNT(*) as transactions, SUM(subtotal + additional_charge_amount) as gross, SUM(discount_amount) as discount, SUM(amount) as collected')
            ->groupBy('branch_id')
            ->get()
            ->map(function ($row) {
                $row->branch_name = Branch::find($row->branch_id)?->name ?? 'Unknown Branch';

                return $row;
            });

        $byMethod = (clone $base())
            ->selectRaw('method, COUNT(*) as transactions, SUM(amount) as amount')
            ->groupBy('method')
            ->get();

        $voidedOrders = MenuOrder::query()
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->where('status', 'voided')
            ->whereDate('voided_at', '>=', $dateFrom)
            ->whereDate('voided_at', '<=', $dateTo)
            ->get();

        return [
            'gross_sales' => $grossSales,
            'total_discount' => $totalDiscount,
            'senior_discount_amount' => $seniorDiscountAmount,
            'pwd_discount_amount' => $pwdDiscountAmount,
            'senior_count' => $seniorCount,
            'pwd_count' => $pwdCount,
            'vat_exempt_sales' => $vatExemptSales,
            'vatable_sales' => $vatableSales,
            'zero_rated_sales' => 0.0,
            'vat_amount' => $vatAmount,
            'net_sales' => $netSales,
            'amount_collected' => $amountCollected,
            'transaction_count' => $transactionCount,
            'customers_served' => $customersServed,
            'sales_by_branch' => $salesByBranch,
            'by_method' => $byMethod,
            'voided_count' => $voidedOrders->count(),
            'voided_amount' => (float) $voidedOrders->sum('total_amount'),
        ];
    }
}
