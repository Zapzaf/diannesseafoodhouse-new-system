<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\MenuOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class TableManagementController extends Controller
{
    private function authorizeBranch(int $branchId): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && (int) $user->branch_id !== $branchId) {
            abort(403);
        }
    }

    public function index(): View
    {
        return view('tables.index');
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['table_number', 'capacity', 'status', 'branch_id', 'current_order_id', 'created_at'];

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $query = DiningTable::with('branch');

        if (!$request->user()->isAdmin()) {
            $query->where('branch_id', $request->user()->branch_id);
        }

        if (in_array($status, ['available', 'occupied', 'reserved', 'cleaning'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('table_number', 'like', "%{$search}%")
                    ->orWhereHas('branch', fn($branch) => $branch->where('name', 'like', "%{$search}%"));
            });
        }

        $tables = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($tables);
    }

    public function create(): View
    {
        $user = auth()->user();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        if (!$user->isAdmin() && !$user->branch_id) {
            return redirect()->route('dashboard')->with('error', 'You must be assigned to a branch before adding tables.');
        }

        return view('tables.create', [
            'branches' => $branches,
            'selectedBranchId' => $user->isAdmin() ? (int) ($branches->first()->id ?? 0) : (int) $user->branch_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'table_number' => 'required|string|max:50|unique:tables,table_number',
            'capacity' => 'required|integer|min:1',
            'status' => 'required|in:available,occupied,reserved,cleaning',
        ]);

        if (!$request->user()->isAdmin() && (int) $data['branch_id'] !== (int) $request->user()->branch_id) {
            abort(403);
        }

        if ($data['status'] === 'available') {
            $data['current_order_id'] = null;
        }

        DiningTable::create($data);

        return redirect()->route('tables.index')->with('success', 'Table added successfully.');
    }

    public function edit(DiningTable $table): View
    {
        $this->authorizeBranch($table->branch_id);
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('tables.edit', [
            'table' => $table,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, DiningTable $table): RedirectResponse
    {
        $this->authorizeBranch($table->branch_id);

        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'table_number' => 'required|string|max:50|unique:tables,table_number,' . $table->id,
            'capacity' => 'required|integer|min:1',
            'status' => 'required|in:available,occupied,reserved,cleaning',
        ]);

        if (!$request->user()->isAdmin() && (int) $data['branch_id'] !== (int) $request->user()->branch_id) {
            abort(403);
        }

        if ($data['status'] === 'available') {
            $data['current_order_id'] = null;
        }

        $table->update($data);

        return redirect()->route('tables.index')->with('success', 'Table updated successfully.');
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        $this->authorizeBranch($table->branch_id);

        if ($table->current_order_id !== null) {
            return back()->with('error', 'Cannot delete a table that is currently assigned to an order.');
        }

        $table->delete();

        return redirect()->route('tables.index')->with('success', 'Table deleted successfully.');
    }

    public function assign(Request $request, DiningTable $table): JsonResponse
    {
        $this->authorizeBranch($table->branch_id);

        $data = $request->validate([
            'order_id' => 'required|integer|exists:menu_orders,id',
        ]);

        if ($table->status !== 'available' || $table->current_order_id !== null) {
            return response()->json(['message' => 'This table is not available for assignment.'], 422);
        }

        $order = MenuOrder::findOrFail((int) $data['order_id']);

        if ($order->branch_id !== $table->branch_id) {
            return response()->json(['message' => 'Order branch does not match this table branch.'], 422);
        }

        if ($order->status !== 'open') {
            return response()->json(['message' => 'Only open orders can be assigned to a table.'], 422);
        }

        DB::transaction(function () use ($table, $order) {
            $table->lockForUpdate();
            $table->current_order_id = $order->id;
            $table->status = 'occupied';
            $table->save();
        });

        return response()->json(['message' => 'Table assigned successfully.']);
    }

    public function release(Request $request, DiningTable $table): JsonResponse
    {
        $this->authorizeBranch($table->branch_id);

        if ($table->current_order_id !== null) {
            $currentOrder = MenuOrder::find($table->current_order_id);

            if ($currentOrder && (string) $currentOrder->status === 'open') {
                return response()->json([
                    'message' => 'This table has an open order with an outstanding balance. Settle, cancel, or void the order before releasing the table.',
                ], 422);
            }
        }

        DB::transaction(function () use ($table) {
            $table->lockForUpdate();
            $table->current_order_id = null;
            $table->status = 'available';
            $table->save();
        });

        return response()->json(['message' => 'Table released successfully.']);
    }
}
