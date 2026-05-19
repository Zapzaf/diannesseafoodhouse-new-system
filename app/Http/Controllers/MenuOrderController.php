<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Menu;
use App\Models\MenuOrder;
use App\Models\MenuOrderItem;
use App\Models\MenuOrderPayment;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuOrderController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    private function branchScope()
    {
        $user = auth()->user();
        $query = MenuOrder::with(['branch', 'items.menu', 'payments']);

        if (!$user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif (session('selected_branch_id')) {
            $query->where('branch_id', session('selected_branch_id'));
        }

        return $query;
    }

    private function authorizeBranch(int $branchId): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && (int) $user->branch_id !== $branchId) {
            abort(403);
        }
    }

    private function assignTableToOrder(MenuOrder $order, ?int $tableId): void
    {
        if (!$tableId) {
            return;
        }

        $table = DiningTable::whereKey($tableId)
            ->where('branch_id', $order->branch_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($table->status !== 'available' || $table->current_order_id !== null) {
            throw ValidationException::withMessages(['table_id' => 'Selected table is not available for assignment.']);
        }

        $table->update([
            'current_order_id' => $order->id,
            'status' => 'occupied',
        ]);
    }

    private function releaseTableForOrder(MenuOrder $order): void
    {
        DiningTable::where('current_order_id', $order->id)
            ->lockForUpdate()
            ->update([
                'current_order_id' => null,
                'status' => 'available',
            ]);
    }

    public function index(): View
    {
        return view('menu-orders.index');
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', 'open'));
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'total_amount', 'amount_paid', 'balance', 'payment_status', 'status'];

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $orders = $this->branchScope()
            ->withCount('payments')
            ->when(in_array($status, ['open', 'completed', 'cancelled'], true), fn($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn($branch) => $branch->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return response()->json($orders);
    }

    public function create(): View|RedirectResponse
    {
        $user = auth()->user();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        $selectedBranchId = $user->isAdmin()
            ? (int) (session('selected_branch_id') ?: ($branches->first()->id ?? 0))
            : (int) $user->branch_id;

        if (!$selectedBranchId && $branches->isEmpty()) {
            return redirect()->route('dashboard')->with('error', 'No branches available. Please create a branch first.');
        }

        $menus = Menu::with('items')
            ->orderBy('name')
            ->get();

        $tables = DiningTable::where('status', 'available')
            ->whereNull('current_order_id')
            ->orderBy('branch_id')
            ->orderBy('table_number')
            ->get();

        return view('menu-orders.create', compact('branches', 'menus', 'selectedBranchId', 'tables'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'branch_id'                => 'required|exists:branches,id',
            'table_id'                 => 'nullable|exists:tables,id',
            'customer_name'            => 'nullable|string|max:255',
            'items'                    => 'required|array|min:1',
            'items.*.menu_id'          => 'required|exists:menus,id',
            'items.*.quantity'         => 'required|integer|min:1|max:999',
            'additional_charge_label'  => 'nullable|string|max:120',
            'additional_charge_amount' => 'nullable|numeric|min:0',
            'regular_pax'              => 'required|integer|min:0|max:999',
            'pwd_pax'                  => 'required|integer|min:0|max:999',
            'senior_pax'               => 'required|integer|min:0|max:999',
            'pwd_ids'                  => 'nullable|array',
            'pwd_ids.*'                => 'nullable|string|max:100',
            'pwd_names'                => 'nullable|array',
            'pwd_names.*'              => 'nullable|string|max:255',
            'senior_ids'               => 'nullable|array',
            'senior_ids.*'             => 'nullable|string|max:100',
            'senior_names'             => 'nullable|array',
            'senior_names.*'           => 'nullable|string|max:255',
            'notes'                    => 'nullable|string',
        ]);

        if (!$user->isAdmin() && (int) $data['branch_id'] !== (int) $user->branch_id) {
            abort(403);
        }

        $branchId = (int) $data['branch_id'];
        $branch = Branch::findOrFail($branchId);

        $createdOrder = DB::transaction(function () use ($data, $branch, $user) {
            $prepared = $this->prepareOrderComputation($data, $branch);

            $order = MenuOrder::create([
                'branch_id' => $branch->id,
                'customer_name' => trim((string) ($data['customer_name'] ?? '')) ?: null,
                'subtotal' => $prepared['subtotal'],
                'additional_charge_label' => $prepared['additional_charge_label'],
                'additional_charge_amount' => $prepared['additional_charge_amount'],
                'regular_pax' => $prepared['regular_pax'],
                'pwd_pax' => $prepared['pwd_pax'],
                'senior_pax' => $prepared['senior_pax'],
                'total_pax' => $prepared['total_pax'],
                'discount_type'       => $prepared['discount_type'],
                'discount_id_number'  => $this->buildDiscountJson($data, 'ids'),
                'discount_name'       => $this->buildDiscountJson($data, 'names'),
                'discount_amount' => $prepared['totals']['discount_amount'],
                'total_vat_exempt' => $prepared['totals']['total_vat_exempt'],
                'vat_rate' => $prepared['totals']['vat_rate'],
                'vat_amount' => $prepared['totals']['vat_amount'],
                'total_amount' => $prepared['totals']['total_amount'],
                'amount_paid' => 0,
                'balance' => $prepared['totals']['total_amount'],
                'payment_status' => 'unpaid',
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $savedItems = $order->items()->createMany($prepared['items_payload']);
            $this->assignTableToOrder($order, isset($data['table_id']) ? (int) $data['table_id'] : null);
            $this->deductInventoryForOrder($order, $savedItems, $prepared['menus_keyed']);

            return $order;
        });

        return redirect()
            ->route('menu-orders.show', $createdOrder)
            ->with('success', 'Menu order created successfully.');
    }

    public function show(MenuOrder $menuOrder): View
    {
        $this->authorizeBranch($menuOrder->branch_id);
        $menuOrder->load(['branch', 'items.menu', 'payments.receivedBy']);

        return view('menu-orders.show', compact('menuOrder'));
    }

    public function edit(MenuOrder $menuOrder): View
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ($menuOrder->payments()->exists()) {
            abort(403, 'Orders with payments can no longer be edited.');
        }

        $menuOrder->load(['items.menu']);
        $user = auth()->user();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $selectedBranchId = $menuOrder->branch_id;

        $menus = Menu::with('items')
            ->orderBy('name')
            ->get();

        $tables = DiningTable::where('branch_id', $selectedBranchId)
            ->where(function ($query) use ($menuOrder) {
                $query->where(function ($query) {
                    $query->where('status', 'available')->whereNull('current_order_id');
                });

                if ($menuOrder->table) {
                    $query->orWhere('current_order_id', $menuOrder->id);
                }
            })
            ->orderBy('table_number')
            ->get();

        return view('menu-orders.create', compact('branches', 'menus', 'selectedBranchId', 'menuOrder', 'tables'))->with('isEdit', true);
    }

    public function update(Request $request, MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ($menuOrder->payments()->exists()) {
            return back()->with('error', 'Orders with payments can no longer be edited.');
        }

        $data = $request->validate([
            'branch_id'                => 'required|exists:branches,id',
            'table_id'                 => 'nullable|exists:tables,id',
            'customer_name'            => 'nullable|string|max:255',
            'items'                    => 'required|array|min:1',
            'items.*.menu_id'          => 'required|exists:menus,id',
            'items.*.quantity'         => 'required|integer|min:1|max:999',
            'additional_charge_label'  => 'nullable|string|max:120',
            'additional_charge_amount' => 'nullable|numeric|min:0',
            'regular_pax'              => 'required|integer|min:0|max:999',
            'pwd_pax'                  => 'required|integer|min:0|max:999',
            'senior_pax'               => 'required|integer|min:0|max:999',
            'pwd_ids'                  => 'nullable|array',
            'pwd_ids.*'                => 'nullable|string|max:100',
            'pwd_names'                => 'nullable|array',
            'pwd_names.*'              => 'nullable|string|max:255',
            'senior_ids'               => 'nullable|array',
            'senior_ids.*'             => 'nullable|string|max:100',
            'senior_names'             => 'nullable|array',
            'senior_names.*'           => 'nullable|string|max:255',
            'notes'                    => 'nullable|string',
        ]);

        $branch = Branch::findOrFail((int) $data['branch_id']);

        DB::transaction(function () use ($menuOrder, $data, $branch) {
            $prepared = $this->prepareOrderComputation($data, $branch);
            $currentTable = DiningTable::where('current_order_id', $menuOrder->id)->lockForUpdate()->first();
            $requestedTableId = isset($data['table_id']) ? (int) $data['table_id'] : null;

            if ($currentTable && $currentTable->id !== $requestedTableId) {
                $currentTable->update(['current_order_id' => null, 'status' => 'available']);
            }

            $menuOrder->update([
                'branch_id' => $branch->id,
                'customer_name' => trim((string) ($data['customer_name'] ?? '')) ?: null,
                'subtotal' => $prepared['subtotal'],
                'additional_charge_label' => $prepared['additional_charge_label'],
                'additional_charge_amount' => $prepared['additional_charge_amount'],
                'regular_pax' => $prepared['regular_pax'],
                'pwd_pax' => $prepared['pwd_pax'],
                'senior_pax' => $prepared['senior_pax'],
                'total_pax' => $prepared['total_pax'],
                'discount_type'       => $prepared['discount_type'],
                'discount_id_number'  => $this->buildDiscountJson($data, 'ids'),
                'discount_name'       => $this->buildDiscountJson($data, 'names'),
                'discount_amount' => $prepared['totals']['discount_amount'],
                'total_vat_exempt' => $prepared['totals']['total_vat_exempt'],
                'vat_rate' => $prepared['totals']['vat_rate'],
                'vat_amount' => $prepared['totals']['vat_amount'],
                'total_amount' => $prepared['totals']['total_amount'],
                'amount_paid' => 0,
                'balance' => $prepared['totals']['total_amount'],
                'payment_status' => 'unpaid',
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
            ]);

            $menuOrder->items()->delete();
            $menuOrder->items()->createMany($prepared['items_payload']);
            if ($requestedTableId && (!$currentTable || $currentTable->id !== $requestedTableId)) {
                $this->assignTableToOrder($menuOrder, $requestedTableId);
            }
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Menu order updated successfully.');
    }

    public function destroy(MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ($menuOrder->payments()->exists()) {
            return back()->with('error', 'Cannot delete an order with recorded payments.');
        }

        DB::transaction(function () use ($menuOrder) {
            $this->releaseTableForOrder($menuOrder);
            $menuOrder->delete();
        });

        return redirect()->route('menu-orders.index')->with('success', 'Menu order deleted successfully.');
    }

    public function storePayment(Request $request, MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        $user = auth()->user();
        $data = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'amount_tendered'  => 'required|numeric|min:0.01',
            'method'           => 'required|in:cash,gcash,card,bank',
            'payment_date'     => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        DB::transaction(function () use ($menuOrder, $data, $user) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();

            if ((string) $lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'Cancelled orders cannot receive payments.']);
            }

            if ((string) $lockedOrder->payment_status === 'paid' || (float) $lockedOrder->balance <= 0) {
                throw ValidationException::withMessages(['amount' => 'This order is already fully paid.']);
            }

            $amount   = round((float) $data['amount'], 2);
            $tendered = round((float) $data['amount_tendered'], 2);
            $balance  = round((float) $lockedOrder->balance, 2);

            // Amount applied to balance is capped at balance; tendered can exceed it
            $applied = min($amount, $balance);
            $change  = max(0, $tendered - $applied);

            $orNumber = 'OR-' . str_pad((string) ((MenuOrderPayment::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);

            MenuOrderPayment::create([
                'branch_id'               => $lockedOrder->branch_id,
                'menu_order_id'           => $lockedOrder->id,
                'amount'                  => $applied,
                'amount_tendered'         => $tendered,
                'change_amount'           => $change,
                'subtotal'                => round((float) $lockedOrder->subtotal, 2),
                'additional_charge_label' => $lockedOrder->additional_charge_label,
                'additional_charge_amount'=> round((float) ($lockedOrder->additional_charge_amount ?? 0), 2),
                'total_vat_exempt'        => round((float) $lockedOrder->total_vat_exempt, 2),
                'total_discount'          => round((float) $lockedOrder->discount_amount, 2),
                'final_total'             => round((float) $lockedOrder->total_amount, 2),
                'discount_type'           => $lockedOrder->discount_type,
                'discount_amount'         => round((float) $lockedOrder->discount_amount, 2),
                'vat_amount'              => round((float) $lockedOrder->vat_amount, 2),
                'is_vat_exempt'           => round((float) $lockedOrder->total_vat_exempt, 2) > 0,
                'method'                  => $data['method'],
                'reference_number'        => $data['reference_number'] ?? null,
                'or_number'               => $orNumber,
                'payment_date'            => $data['payment_date'],
                'notes'                   => $data['notes'] ?? null,
                'received_by'             => $user->id,
            ]);

            $amountPaid = round((float) $lockedOrder->payments()->sum('amount'), 2);
            $totalAmount = round((float) $lockedOrder->total_amount, 2);
            $newBalance = round(max(0, $totalAmount - $amountPaid), 2);

            $lockedOrder->update([
                'amount_paid' => $amountPaid,
                'balance' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
                'status' => $newBalance <= 0 ? 'completed' : 'open',
            ]);
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Payment recorded successfully.');
    }

    public function complete(MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ((string) $menuOrder->status === 'completed') {
            return back()->with('error', 'This order is already completed.');
        }

        if ((string) $menuOrder->status === 'cancelled') {
            return back()->with('error', 'Cannot complete a cancelled order.');
        }

        DB::transaction(function () use ($menuOrder) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();

            $amountPaid = round((float) $lockedOrder->payments()->sum('amount'), 2);
            $totalAmount = round((float) $lockedOrder->total_amount, 2);
            $newBalance = round(max(0, $totalAmount - $amountPaid), 2);

            $lockedOrder->update([
                'amount_paid' => $amountPaid,
                'balance' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
                'status' => 'completed',
            ]);

            $this->releaseTableForOrder($lockedOrder);
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Order marked as completed.');
    }

    public function cancel(MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ((string) $menuOrder->status === 'cancelled') {
            return back()->with('error', 'This order is already cancelled.');
        }

        if ($menuOrder->payments()->exists()) {
            return back()->with('error', 'Cannot cancel an order with recorded payments.');
        }

        DB::transaction(function () use ($menuOrder) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->update([
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
                'amount_paid' => 0,
                'balance' => round((float) $lockedOrder->total_amount, 2),
            ]);

            $this->releaseTableForOrder($lockedOrder);
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Order cancelled.');
    }

    public function paymentReceipt(MenuOrderPayment $payment): View
    {
        $this->authorizeBranch($payment->branch_id);
        $payment->load(['order.branch', 'order.items.menu', 'receivedBy']);

        return view('menu-orders.receipt', compact('payment'));
    }

    /**
     * Deduct inventory ingredients for every ordered item.
     * Skips items whose ingredients are already deducted or have no recipe.
     */
    private function deductInventoryForOrder(MenuOrder $order, $savedItems, $menusKeyed): void
    {
        $userId = auth()->id();
        $now    = now();

        foreach ($savedItems as $orderItem) {
            if ($orderItem->inventory_deducted) {
                continue;
            }

            /** @var Menu|null $menu */
            $menu = $menusKeyed->get($orderItem->menu_id);
            if (! $menu || $menu->items->isEmpty()) {
                continue;
            }

            $allDeducted = true;

            foreach ($menu->items as $ingredient) {
                $needed = round((float) $ingredient->pivot->quantity_required * $orderItem->quantity, 4);
                if ($needed <= 0) {
                    continue;
                }

                // Lock the inventory row to prevent race conditions
                $stock = Item::whereKey($ingredient->id)
                    ->where('branch_id', $order->branch_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || (float) $stock->quantity < $needed) {
                    // Insufficient stock — skip deduction for this ingredient
                    $allDeducted = false;
                    continue;
                }

                $beginning = (float) $stock->quantity;
                $this->inventoryService->decrease($stock, $needed);
                $remaining = (float) $stock->quantity;

                InventoryTransaction::create([
                    'item_id'            => $stock->id,
                    'branch_id'          => $order->branch_id,
                    'type'               => 'out',
                    'quantity'           => $needed,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price'  => $stock->unit_price ? (float) $stock->unit_price * $needed : null,
                    'transaction_date'   => $now,
                    'reason'             => 'Sale — Menu Order #' . $order->id,
                    'status'             => 'approved',
                    'notes'              => 'Auto-deducted from menu order.',
                    'created_by'         => $userId,
                ]);
            }

            $orderItem->update(['inventory_deducted' => $allDeducted]);
        }
    }

    private function prepareOrderComputation(array $data, Branch $branch): array
    {
        $menus = Menu::whereIn('id', collect($data['items'])->pluck('menu_id')->all())
            ->where('branch_id', $branch->id)
            ->with('items')
            ->get()
            ->keyBy('id');

        $itemsPayload = [];
        $subtotal = 0.0;

        foreach ($data['items'] as $row) {
            $menu = $menus->get((int) $row['menu_id']);
            if (!$menu) {
                throw ValidationException::withMessages([
                    'items' => 'One or more menu items are invalid for the selected branch.',
                ]);
            }

            $quantity = (int) $row['quantity'];
            $unitPrice = round((float) $menu->selling_price, 2);
            $lineSubtotal = round($unitPrice * $quantity, 2);
            $unitCost = round((float) $menu->computeUnitCost(), 2);
            $lineCost = round($unitCost * $quantity, 2);

            $itemsPayload[] = [
                'menu_id'            => $menu->id,
                'quantity'           => $quantity,
                'unit_price'         => $unitPrice,
                'subtotal'           => $lineSubtotal,
                'cost'               => $lineCost,
                'profit'             => round($lineSubtotal - $lineCost, 2),
                'inventory_deducted' => false,
            ];

            $subtotal += $lineSubtotal;
        }

        $subtotal = round($subtotal, 2);
        $additionalChargeAmount = round((float) ($data['additional_charge_amount'] ?? 0), 2);
        $additionalChargeLabel = trim((string) ($data['additional_charge_label'] ?? ''));
        if ($additionalChargeAmount <= 0) {
            $additionalChargeAmount = 0.0;
            $additionalChargeLabel = null;
        }

        $regularPax = max(0, (int) ($data['regular_pax'] ?? 0));
        $pwdPax = max(0, (int) ($data['pwd_pax'] ?? 0));
        $seniorPax = max(0, (int) ($data['senior_pax'] ?? 0));
        $totalPax = $regularPax + $pwdPax + $seniorPax;
        if ($totalPax <= 0) {
            $regularPax = 1;
            $totalPax = 1;
        }

        $vatEnabled = (bool) ($branch->vat_enabled ?? true);
        if (!$vatEnabled) {
            $pwdPax = 0;
            $seniorPax = 0;
        }

        $discountType = 'none';
        if ($vatEnabled && $pwdPax > 0 && $seniorPax > 0) {
            $discountType = 'mixed';
        } elseif ($vatEnabled && $pwdPax > 0) {
            $discountType = 'pwd';
        } elseif ($vatEnabled && $seniorPax > 0) {
            $discountType = 'senior';
        }

        $totals = $this->computeTotals(
            $branch, $subtotal, $additionalChargeAmount,
            $regularPax, $pwdPax, $seniorPax, $totalPax, $vatEnabled
        );

        return [
            'items_payload'           => $itemsPayload,
            'menus_keyed'             => $menus,
            'subtotal'                => $subtotal,
            'additional_charge_amount'=> $additionalChargeAmount,
            'additional_charge_label' => $additionalChargeLabel,
            'regular_pax'             => $regularPax,
            'pwd_pax'                 => $pwdPax,
            'senior_pax'              => $seniorPax,
            'total_pax'               => $totalPax,
            'discount_type'           => $discountType,
            'totals'                  => $totals,
        ];
    }

    private function computeTotals(
        Branch $branch, float $subtotal, float $additionalChargeAmount,
        int $regularPax, int $pwdPax, int $seniorPax, int $totalPax, bool $vatEnabled
    ): array {
        $vatRate = (float) ($branch->vat_percentage ?? 12.00);
        $gross = round($subtotal + $additionalChargeAmount, 2);

        $discountedPax = $vatEnabled ? ($pwdPax + $seniorPax) : 0;
        $perPaxGross = round($gross / max(1, $totalPax), 2);
        $totalVatExempt = round($perPaxGross * $discountedPax, 2);
        $discountAmount = round($totalVatExempt * 0.20, 2);

        $vatAmount = 0.0;
        if ($vatEnabled) {
            $vatableGross = round(max(0, $gross - $totalVatExempt), 2);
            $vatAmount = $vatRate > 0 ? round($vatableGross * ($vatRate / (100 + $vatRate)), 2) : 0.0;
        }

        $totalAmount = round(max(0, $gross - $discountAmount), 2);

        return [
            'vat_rate' => $vatRate,
            'discount_amount' => $discountAmount,
            'total_vat_exempt' => $totalVatExempt,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function buildDiscountJson(array $data, string $field): ?string
    {
        $pwdKey    = $field === 'ids' ? 'pwd_ids'    : 'pwd_names';
        $seniorKey = $field === 'ids' ? 'senior_ids' : 'senior_names';
        $pwd    = array_values(array_filter((array) ($data[$pwdKey]    ?? []), fn($v) => $v !== null && $v !== ''));
        $senior = array_values(array_filter((array) ($data[$seniorKey] ?? []), fn($v) => $v !== null && $v !== ''));
        $result = [];
        if (!empty($pwd))    $result['pwd']    = $pwd;
        if (!empty($senior)) $result['senior'] = $senior;
        return !empty($result) ? json_encode($result) : null;
    }
}