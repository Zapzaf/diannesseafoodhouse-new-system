<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashShift;
use App\Models\MenuOrderPayment;
use App\Models\PosTerminal;
use App\Models\ZReading;
use App\Exports\ZReadingExport;
use App\Services\SalesReadingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ZReadingController extends Controller
{
    public function __construct(private readonly SalesReadingService $readingService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $user = $request->user();
            $branchId = $this->activeBranchId($request);

            $readings = ZReading::query()
                ->with(['branch', 'terminal', 'generatedBy'])
                ->when(!$user->isAdmin(), fn (Builder $q) => $q->where('branch_id', $user->branch_id))
                ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
                ->latest('generated_at')
                ->paginate($this->perPage($request, 20))
                ->withQueryString();

            $terminals = PosTerminal::query()
                ->where('is_active', true)
                ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
                ->orderBy('name')
                ->get();

            return view('reports.z-reading-index', [
                'readings' => $readings,
                'terminals' => $terminals,
                'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
                'selectedBranchId' => $branchId,
                'today' => now()->toDateString(),
            ]);
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading list', route('dashboard'));
        }
    }

    public function preview(Request $request): View|RedirectResponse
    {
        try {
            $data = $this->validateTerminalAndDate($request);
            $terminal = $this->resolveActiveTerminal($request, $data['pos_terminal_id']);

            $paymentsQuery = $this->paymentsQuery($terminal->id, $data['business_date']);
            $summary = $this->readingService->summarize($paymentsQuery, $terminal->branch_id, $data['business_date'], $data['business_date']);

            $existing = ZReading::where('pos_terminal_id', $terminal->id)
                ->where('business_date', $data['business_date'])
                ->where('status', 'locked')
                ->first();

            $hasOpenShift = CashShift::where('pos_terminal_id', $terminal->id)->where('status', 'open')->exists();
            $hasTransactions = $summary['transaction_count'] > 0;

            return view('reports.z-reading-preview', [
                'terminal' => $terminal,
                'businessDate' => $data['business_date'],
                'summary' => $summary,
                'existing' => $existing,
                'hasOpenShift' => $hasOpenShift,
                'hasTransactions' => $hasTransactions,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading preview', route('reports.z-reading.index'));
        }
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $this->validateTerminalAndDate($request);
            $terminal = $this->resolveActiveTerminal($request, $data['pos_terminal_id']);

            $reading = DB::transaction(function () use ($terminal, $data, $request): ZReading {
                $alreadyLocked = ZReading::where('pos_terminal_id', $terminal->id)
                    ->where('business_date', $data['business_date'])
                    ->where('status', 'locked')
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyLocked) {
                    throw ValidationException::withMessages([
                        'business_date' => 'A Z Reading has already been generated and locked for this terminal and date. An administrator must void it before a new one can be generated.',
                    ]);
                }

                if (CashShift::where('pos_terminal_id', $terminal->id)->where('status', 'open')->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'business_date' => 'This terminal still has an open cash shift. Close all shifts for the day before generating a Z Reading.',
                    ]);
                }

                $paymentsQuery = $this->paymentsQuery($terminal->id, $data['business_date']);
                $summary = $this->readingService->summarize($paymentsQuery, $terminal->branch_id, $data['business_date'], $data['business_date']);

                if ((int) $summary['transaction_count'] === 0) {
                    throw ValidationException::withMessages([
                        'business_date' => 'There are no recorded transactions for this terminal on the selected business date. A Z Reading cannot be generated for a day with no sales.',
                    ]);
                }

                // Re-derive the next sequence number inside the lock so two
                // concurrent generations for different (already-void) rows
                // on this terminal can never collide on the same number.
                $sequence = ZReading::where('pos_terminal_id', $terminal->id)->lockForUpdate()->count() + 1;

                return ZReading::create([
                    'branch_id' => $terminal->branch_id,
                    'pos_terminal_id' => $terminal->id,
                    'business_date' => $data['business_date'],
                    'sequence_number' => $sequence,
                    'reading_number' => ZReading::makeReadingNumber($terminal->id, $sequence),
                    'generated_by' => $request->user()->id,
                    'generated_at' => now(),
                    'snapshot' => $this->serializeSummary($summary),
                    'status' => 'locked',
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            if ($this->isDuplicateLockViolation($e)) {
                Log::warning('Z Reading generation raced a concurrent request for the same terminal/date.', [
                    'pos_terminal_id' => $request->input('pos_terminal_id'),
                    'business_date' => $request->input('business_date'),
                    'user_id' => $request->user()?->id,
                ]);

                return back()
                    ->withErrors(['business_date' => 'A Z Reading for this terminal and date was just generated by another request. Please refresh and check the existing reading.'])
                    ->withInput();
            }

            return $this->failGracefully($e, 'Z Reading generation', route('reports.z-reading.index'));
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading generation', route('reports.z-reading.index'));
        }

        return redirect()->route('reports.z-reading.show', $reading)->with('success', 'Z Reading ' . $reading->reading_number . ' generated and locked.');
    }

    public function show(Request $request, ZReading $zReading): View|RedirectResponse
    {
        try {
            $this->authorizeBranchRecord($request, $zReading->branch_id);
            $zReading->load(['branch', 'terminal', 'generatedBy', 'voidedBy']);

            if (!is_array($zReading->snapshot) || empty($zReading->snapshot)) {
                Log::error('Z Reading has a missing or corrupt snapshot.', ['z_reading_id' => $zReading->id]);

                return redirect()->route('reports.z-reading.index')
                    ->with('error', 'This Z Reading\'s stored data is missing or corrupted. Please contact an administrator.');
            }

            return view('reports.z-reading-show', [
                'reading' => $zReading,
                'summary' => $zReading->snapshot,
            ]);
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading detail', route('reports.z-reading.index'));
        }
    }

    public function void(Request $request, ZReading $zReading): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            abort(403, 'Only an administrator can void a Z Reading.');
        }

        try {
            $data = $request->validate([
                'void_reason' => 'required|string|max:500',
            ]);

            DB::transaction(function () use ($zReading, $data, $user): void {
                $locked = ZReading::whereKey($zReading->id)->lockForUpdate()->firstOrFail();

                if (!$locked->isLocked()) {
                    throw ValidationException::withMessages(['void_reason' => 'This Z Reading is already voided.']);
                }

                $locked->update([
                    'status' => 'voided',
                    'voided_at' => now(),
                    'voided_by' => $user->id,
                    'void_reason' => $data['void_reason'],
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading void', route('reports.z-reading.show', $zReading));
        }

        Log::info('Z Reading voided.', [
            'z_reading_id' => $zReading->id,
            'reading_number' => $zReading->reading_number,
            'voided_by' => $user->id,
        ]);

        return back()->with('success', 'Z Reading voided. A new one can now be generated for this terminal and date.');
    }

    public function exportExcel(Request $request, ZReading $zReading)
    {
        try {
            $this->authorizeBranchRecord($request, $zReading->branch_id);

            return Excel::download(
                new ZReadingExport($zReading),
                $zReading->reading_number . '.xlsx'
            );
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading Excel export', route('reports.z-reading.show', $zReading));
        }
    }

    public function exportPdf(Request $request, ZReading $zReading): Response|RedirectResponse
    {
        try {
            $this->authorizeBranchRecord($request, $zReading->branch_id);
            $zReading->load(['branch', 'terminal', 'generatedBy']);

            $html = view('reports.z-reading-pdf', [
                'reading' => $zReading,
                'summary' => $zReading->snapshot,
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
                'Content-Disposition' => 'attachment; filename="' . $zReading->reading_number . '.pdf"',
            ]);
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Z Reading PDF export', route('reports.z-reading.show', $zReading));
        }
    }

    /**
     * @return array{pos_terminal_id: int, business_date: string}
     */
    private function validateTerminalAndDate(Request $request): array
    {
        return $request->validate([
            'pos_terminal_id' => 'required|integer|exists:pos_terminals,id',
            'business_date' => 'required|date|before_or_equal:today',
        ], [
            'pos_terminal_id.required' => 'Please select a POS terminal.',
            'pos_terminal_id.exists' => 'The selected POS terminal does not exist.',
            'business_date.required' => 'Please select a business date.',
            'business_date.date' => 'The business date is not a valid date.',
            'business_date.before_or_equal' => 'The business date cannot be in the future.',
        ]);
    }

    private function resolveActiveTerminal(Request $request, int $terminalId): PosTerminal
    {
        $terminal = PosTerminal::find($terminalId);

        if (!$terminal) {
            throw ValidationException::withMessages(['pos_terminal_id' => 'The selected POS terminal does not exist.']);
        }

        if (!$terminal->is_active) {
            throw ValidationException::withMessages(['pos_terminal_id' => 'The selected POS terminal is inactive and cannot generate readings.']);
        }

        if (!$terminal->branch) {
            throw ValidationException::withMessages(['pos_terminal_id' => 'The selected POS terminal is not linked to a valid branch.']);
        }

        $this->authorizeBranchRecord($request, $terminal->branch_id);

        return $terminal;
    }

    private function paymentsQuery(int $terminalId, string $businessDate): Builder
    {
        return MenuOrderPayment::query()
            ->where('pos_terminal_id', $terminalId)
            ->whereDate('payment_date', $businessDate);
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function serializeSummary(array $summary): array
    {
        foreach ($summary as $key => $value) {
            if ($value instanceof Collection) {
                $summary[$key] = $value->toArray();
            }
        }

        return $summary;
    }

    private function isDuplicateLockViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'z_readings_active_lock_unique');
    }

    /**
     * Log the real exception for diagnosis and show the user a plain,
     * non-technical message instead of letting the framework's default
     * exception page (or a raw stack trace) surface.
     */
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
