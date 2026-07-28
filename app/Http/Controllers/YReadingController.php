<?php

namespace App\Http\Controllers;

use App\Exports\MenuOrderSalesReportExport;
use App\Models\CashShift;
use App\Services\SalesReadingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class YReadingController extends Controller
{
    public function __construct(private readonly SalesReadingService $readingService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $shift = $this->resolveShift($request);

            if (!$shift) {
                return redirect()->route('cash-shifts.index')->with('error', 'Select a valid shift to generate its Y Reading.');
            }

            $this->authorizeBranchRecord($request, $shift->branch_id);

            [$summary, $payments] = $this->buildData($shift);

            return view('reports.y-reading', [
                'shift' => $shift,
                'summary' => $summary,
                'payments' => $payments,
                'expectedCash' => $shift->isOpen() ? $shift->computeExpectedCash() : (float) $shift->expected_cash,
                'generatedAt' => now(),
            ]);
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Y Reading', route('cash-shifts.index'));
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $shift = $this->resolveShift($request);

            if (!$shift) {
                return redirect()->route('cash-shifts.index')->with('error', 'Select a valid shift to export its Y Reading.');
            }

            $this->authorizeBranchRecord($request, $shift->branch_id);

            [$summary, $payments] = $this->buildData($shift);

            return Excel::download(new MenuOrderSalesReportExport($payments, $summary), 'y-reading-shift-' . $shift->id . '.xlsx');
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Y Reading Excel export', route('cash-shifts.index'));
        }
    }

    public function exportPdf(Request $request): Response|RedirectResponse
    {
        try {
            $shift = $this->resolveShift($request);

            if (!$shift) {
                return redirect()->route('cash-shifts.index')->with('error', 'Select a valid shift to export its Y Reading.');
            }

            $this->authorizeBranchRecord($request, $shift->branch_id);

            [$summary] = $this->buildData($shift);

            $html = view('reports.y-reading-pdf', [
                'shift' => $shift,
                'summary' => $summary,
                'expectedCash' => $shift->isOpen() ? $shift->computeExpectedCash() : (float) $shift->expected_cash,
                'generatedAt' => now(),
            ])->render();

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Helvetica');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();

            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="y-reading-shift-' . $shift->id . '.pdf"',
            ]);
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Y Reading PDF export', route('cash-shifts.index'));
        }
    }

    private function resolveShift(Request $request): ?CashShift
    {
        $id = $request->integer('cash_shift_id') ?: null;

        return $id ? CashShift::find($id) : null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: \Illuminate\Support\Collection}
     */
    private function buildData(CashShift $shift): array
    {
        $paymentsQuery = $shift->payments()->getQuery();

        $payments = (clone $paymentsQuery)
            ->with(['order:id,order_number,customer_name', 'branch', 'receivedBy'])
            ->orderByDesc('payment_date')
            ->get();

        $summary = $this->readingService->summarize(clone $paymentsQuery, $shift->branch_id);

        return [$summary, $payments];
    }

    private function failGracefully(Throwable $e, string $action, string $redirectTo): RedirectResponse
    {
        Log::error($action . ' failed unexpectedly.', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth()->id(),
        ]);

        return redirect($redirectTo)->with('error', 'Something went wrong while processing the ' . strtolower($action) . '. Please try again, and contact support if the problem continues.');
    }
}
