<?php

namespace App\Http\Controllers;

use App\Exports\MenuOrderSalesReportExport;
use App\Models\Branch;
use App\Models\MenuOrderPayment;
use App\Services\SalesReadingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MenuOrderSalesReportController extends Controller
{
    public const PAYMENT_METHODS = ['cash', 'gcash', 'card', 'bank'];

    public function __construct(private readonly SalesReadingService $readingService)
    {
    }

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

    /**
     * JSON feed for the Transactions table (see
     * ReportController::deliveryData() docblock for why).
     */
    public function data(Request $request): JsonResponse
    {
        $branchId = $this->activeBranchId($request);
        [$dateFrom, $dateTo] = $this->validatedDateRange($request);
        $method = $request->validate(['payment_method' => ['nullable', 'in:' . implode(',', self::PAYMENT_METHODS)]])['payment_method'] ?? '';

        $payments = $this->paymentsQuery($branchId, $dateFrom, $dateTo, $method)
            ->with(['order:id,order_number,customer_name,total_pax,status', 'branch', 'receivedBy'])
            ->orderByDesc('payment_date')
            ->latest()
            ->paginate($this->perPage($request, 20))
            ->through(fn (MenuOrderPayment $payment) => [
                'id' => $payment->id,
                'or_number' => $payment->or_number,
                'date' => (optional($payment->payment_date) ?: $payment->created_at)->format('M d, Y'),
                'branch' => $payment->branch?->name,
                'order_number' => $payment->order?->order_number,
                'customer' => $payment->order?->customer_name ?: 'Walk-in',
                'method' => $payment->method,
                'discount_type' => $payment->discount_type,
                'discount_amount' => (float) $payment->discount_amount,
                'promo_discount_amount' => (float) $payment->promo_discount_amount,
                'promo_discount_label' => $payment->promo_discount_label,
                'promo_discount_source' => $payment->promo_discount_source,
                'gross' => (float) ($payment->subtotal + $payment->additional_charge_amount),
                'vat' => (float) $payment->vat_amount,
                'net' => (float) $payment->amount,
                'received_by' => $payment->receivedBy?->name,
            ]);

        return response()->json($payments);
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
        return $this->readingService->summarize(
            $this->paymentsQuery($branchId, $dateFrom, $dateTo, $method),
            $branchId,
            $dateFrom,
            $dateTo
        );
    }
}
