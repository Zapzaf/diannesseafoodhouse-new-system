<?php

namespace App\Http\Controllers;

use App\Exports\MenuOrderSalesReportExport;
use App\Models\Branch;
use App\Models\MenuOrderPayment;
use App\Models\PosTerminal;
use App\Services\SalesReadingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class XReadingController extends Controller
{
    public function __construct(private readonly SalesReadingService $readingService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            [$branchId, $terminalId, $businessDate] = $this->resolveFilters($request);

            $payments = $this->paymentsQuery($branchId, $terminalId, $businessDate)
                ->with(['order:id,order_number,customer_name', 'branch', 'terminal', 'receivedBy'])
                ->orderByDesc('payment_date')
                ->latest()
                ->get();

            $summary = $this->readingService->summarize(
                $this->paymentsQuery($branchId, $terminalId, $businessDate),
                $branchId,
                $businessDate,
                $businessDate
            );

            return view('reports.x-reading', [
                'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
                'terminals' => PosTerminal::query()->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->orderBy('name')->get(),
                'selectedBranchId' => $branchId,
                'selectedTerminalId' => $terminalId,
                'businessDate' => $businessDate,
                'summary' => $summary,
                'payments' => $payments,
                'generatedAt' => now(),
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'X Reading', route('dashboard'));
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            [$branchId, $terminalId, $businessDate] = $this->resolveFilters($request);

            $payments = $this->paymentsQuery($branchId, $terminalId, $businessDate)
                ->with(['order:id,order_number,customer_name', 'branch', 'receivedBy'])
                ->orderByDesc('payment_date')
                ->get();

            $summary = $this->readingService->summarize($this->paymentsQuery($branchId, $terminalId, $businessDate), $branchId, $businessDate, $businessDate);

            return Excel::download(new MenuOrderSalesReportExport($payments, $summary), 'x-reading-' . $businessDate . '.xlsx');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'X Reading Excel export', route('reports.x-reading.index'));
        }
    }

    public function exportPdf(Request $request): Response|RedirectResponse
    {
        try {
            [$branchId, $terminalId, $businessDate] = $this->resolveFilters($request);

            $summary = $this->readingService->summarize($this->paymentsQuery($branchId, $terminalId, $businessDate), $branchId, $businessDate, $businessDate);

            $html = view('reports.x-reading-pdf', [
                'summary' => $summary,
                'businessDate' => $businessDate,
                'branchName' => $branchId ? (Branch::find($branchId)?->name ?? 'Unknown Branch') : 'All Branches',
                'terminalName' => $terminalId ? (PosTerminal::find($terminalId)?->name ?? 'Unknown Terminal') : 'All Terminals',
                'generatedAt' => now(),
            ])->render();

            return $this->streamPdf($html, 'x-reading-' . $businessDate . '.pdf');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'X Reading PDF export', route('reports.x-reading.index'));
        }
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: string}
     */
    private function resolveFilters(Request $request): array
    {
        $branchId = $this->activeBranchId($request);

        $validated = $request->validate([
            'pos_terminal_id' => 'nullable|integer|exists:pos_terminals,id',
            'business_date' => 'nullable|date|before_or_equal:today',
        ], [
            'pos_terminal_id.exists' => 'The selected POS terminal does not exist.',
            'business_date.date' => 'The business date is not a valid date.',
            'business_date.before_or_equal' => 'The business date cannot be in the future.',
        ]);

        $terminalId = $validated['pos_terminal_id'] ?? null;

        if ($terminalId) {
            $terminal = PosTerminal::find($terminalId);

            if (!$terminal) {
                throw ValidationException::withMessages(['pos_terminal_id' => 'The selected POS terminal does not exist.']);
            }

            $this->authorizeBranchRecord($request, $terminal->branch_id);
        }

        $businessDate = $validated['business_date'] ?? now()->toDateString();

        return [$branchId, $terminalId, $businessDate];
    }

    private function paymentsQuery(?int $branchId, ?int $terminalId, string $businessDate): Builder
    {
        return MenuOrderPayment::query()
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($terminalId, fn (Builder $q, int $id) => $q->where('pos_terminal_id', $id))
            ->whereDate('payment_date', $businessDate);
    }

    private function streamPdf(string $html, string $filename): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
