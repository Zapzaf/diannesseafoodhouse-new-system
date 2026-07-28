<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PosTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosTerminalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = $this->activeBranchId($request);

        $terminals = PosTerminal::query()
            ->with('branch')
            ->when(!$user->isAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        return view('pos-terminals.index', [
            'terminals' => $terminals,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
        ]);

        if (!$user->isAdmin() && (int) $data['branch_id'] !== (int) $user->branch_id) {
            abort(403);
        }

        $exists = PosTerminal::where('branch_id', $data['branch_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'A terminal with this code already exists in the selected branch.'])->withInput();
        }

        PosTerminal::create($data);

        return redirect()->route('pos-terminals.index')->with('success', 'POS terminal created successfully.');
    }

    public function toggleActive(Request $request, PosTerminal $posTerminal): RedirectResponse
    {
        $this->authorizeBranchRecord($request, $posTerminal->branch_id);

        $posTerminal->update(['is_active' => !$posTerminal->is_active]);

        return back()->with('success', 'POS terminal ' . ($posTerminal->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(Request $request, PosTerminal $posTerminal): RedirectResponse
    {
        $this->authorizeBranchRecord($request, $posTerminal->branch_id);

        if ($posTerminal->cashShifts()->exists()) {
            return back()->with('error', 'Cannot delete a terminal that has cash shift history. Deactivate it instead.');
        }

        $posTerminal->delete();

        return redirect()->route('pos-terminals.index')->with('success', 'POS terminal deleted.');
    }
}
