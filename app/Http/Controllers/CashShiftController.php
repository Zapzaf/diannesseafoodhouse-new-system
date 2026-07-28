<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PosTerminal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CashShiftController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = $this->activeBranchId($request);
        $status = $request->validate(['status' => ['nullable', 'in:open,closed']])['status'] ?? '';

        $shifts = CashShift::query()
            ->with(['branch', 'terminal', 'cashier'])
            ->when(!$user->isAdmin(), fn (Builder $q) => $q->where('branch_id', $user->branch_id))
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->latest('opened_at')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        $myOpenShift = CashShift::query()
            ->where('cashier_id', $user->id)
            ->where('status', 'open')
            ->first();

        return view('cash-shifts.index', [
            'shifts' => $shifts,
            'status' => $status,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
            'myOpenShift' => $myOpenShift,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $branchId = $user->isAdmin() ? ($request->session()->get('selected_branch_id') ?: null) : $user->branch_id;

        // Belt-and-suspenders: branches created before terminal
        // auto-provisioning existed (or that somehow still have none) get
        // their default terminal filled in here too, so this page never
        // shows "no terminals available" for a single, known branch.
        if ($branchId) {
            PosTerminal::ensureDefaultForBranch((int) $branchId);
        }

        $terminals = PosTerminal::query()
            ->where('is_active', true)
            ->when($branchId, fn (Builder $q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        return view('cash-shifts.create', [
            'terminals' => $terminals,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'pos_terminal_id' => 'required|exists:pos_terminals,id',
            'opening_float' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $terminal = PosTerminal::findOrFail($data['pos_terminal_id']);
        $this->authorizeBranchRecord($request, $terminal->branch_id);

        try {
            $shift = DB::transaction(function () use ($terminal, $data, $user): CashShift {
                if (CashShift::where('cashier_id', $user->id)->where('status', 'open')->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['pos_terminal_id' => 'You already have an open shift. Close it before opening a new one.']);
                }

                if (CashShift::where('pos_terminal_id', $terminal->id)->where('status', 'open')->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['pos_terminal_id' => 'This terminal already has an open shift with another cashier.']);
                }

                return CashShift::create([
                    'branch_id' => $terminal->branch_id,
                    'pos_terminal_id' => $terminal->id,
                    'cashier_id' => $user->id,
                    'opening_float' => $data['opening_float'],
                    'opened_at' => now(),
                    'opened_by' => $user->id,
                    'status' => 'open',
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Shift open', route('cash-shifts.create'));
        }

        return redirect()->route('cash-shifts.show', $shift)->with('success', 'Shift opened successfully.');
    }

    public function show(Request $request, CashShift $cashShift): View
    {
        $this->authorizeBranchRecord($request, $cashShift->branch_id);
        $cashShift->load(['branch', 'terminal', 'cashier', 'openedBy', 'closedBy', 'movements.recordedBy']);

        return view('cash-shifts.show', [
            'shift' => $cashShift,
            'cashSales' => $cashShift->cashSales(),
            'cashIn' => $cashShift->cashIn(),
            'cashOut' => $cashShift->cashOut(),
            'expectedCash' => $cashShift->isOpen() ? $cashShift->computeExpectedCash() : (float) $cashShift->expected_cash,
        ]);
    }

    public function cashMovement(Request $request, CashShift $cashShift): RedirectResponse
    {
        $this->authorizeBranchRecord($request, $cashShift->branch_id);

        $data = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($cashShift, $data, $request): void {
                $locked = CashShift::whereKey($cashShift->id)->lockForUpdate()->firstOrFail();

                if (!$locked->isOpen()) {
                    throw ValidationException::withMessages(['amount' => 'Cannot record cash movements on a closed shift.']);
                }

                $locked->movements()->create([
                    'type' => $data['type'],
                    'amount' => $data['amount'],
                    'reason' => $data['reason'],
                    'recorded_by' => $request->user()->id,
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Cash movement', route('cash-shifts.show', $cashShift));
        }

        return back()->with('success', 'Cash ' . ($data['type'] === 'in' ? 'in' : 'out') . ' recorded.');
    }

    public function close(Request $request, CashShift $cashShift): RedirectResponse
    {
        $this->authorizeBranchRecord($request, $cashShift->branch_id);

        $data = $request->validate([
            'closing_cash_counted' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($cashShift, $data, $request): void {
                $locked = CashShift::whereKey($cashShift->id)->lockForUpdate()->firstOrFail();

                if (!$locked->isOpen()) {
                    throw ValidationException::withMessages(['closing_cash_counted' => 'This shift is already closed.']);
                }

                $expected = $locked->computeExpectedCash();
                $variance = round((float) $data['closing_cash_counted'] - $expected, 2);

                $locked->update([
                    'closing_cash_counted' => $data['closing_cash_counted'],
                    'expected_cash' => $expected,
                    'cash_variance' => $variance,
                    'status' => 'closed',
                    'closed_at' => now(),
                    'closed_by' => $request->user()->id,
                    'notes' => trim(($locked->notes ? $locked->notes . "\n" : '') . ($data['notes'] ?? '')) ?: $locked->notes,
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (Throwable $e) {
            return $this->failGracefully($e, 'Shift close', route('cash-shifts.show', $cashShift));
        }

        return redirect()->route('cash-shifts.show', $cashShift)->with('success', 'Shift closed successfully.');
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
