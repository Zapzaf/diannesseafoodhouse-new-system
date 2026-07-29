<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashShift;
use App\Models\DiningTable;
use App\Models\DiscountCampaign;
use App\Models\DiscountRedemption;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Menu;
use App\Models\MenuOrder;
use App\Models\MenuOrderItem;
use App\Models\MenuOrderPayment;
use App\Models\ZReading;
use App\Services\DiscountCampaignService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuOrderController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly DiscountCampaignService $discountCampaignService,
    ) {}

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

    /**
     * Items/details are normally locked once a payment is recorded, to
     * protect billing integrity. An admin-reactivated order (previously
     * completed) explicitly opts back into being editable, and Admins can
     * always edit regardless — they're the ones authorized to correct
     * mistakes (e.g. an accidental additional charge) after the fact.
     */
    private function itemsLockedByPayments(MenuOrder $order): bool
    {
        if (auth()->user()?->isAdmin()) {
            return false;
        }

        return $order->payments()->exists() && !$order->is_reactivated;
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

        $orders->getCollection()->transform(function (MenuOrder $order) {
            $order->setAttribute('order_number', $order->orderNumber());

            return $order;
        });

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

        return view('menu-orders.create', compact('branches', 'menus', 'selectedBranchId', 'tables', 'user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $payload = $request->all();
        $payload['additional_charges'] = $this->normalizeAdditionalChargeInput(
            $request->input('additional_charges', []),
            $request->input('additional_charge_label'),
            $request->input('additional_charge_amount')
        );

        $data = validator($payload, [
            'branch_id'                => 'required|exists:branches,id',
            'table_id'                 => 'nullable|exists:tables,id',
            'customer_name'            => 'nullable|string|max:255',
            'items'                    => 'required|array|min:1',
            'items.*.id'               => 'nullable|integer|exists:menu_order_items,id',
            'items.*.menu_id'          => 'required|exists:menus,id',
            'items.*.quantity'         => 'required|integer|min:1|max:999',
            'additional_charges'       => 'nullable|array',
            'additional_charges.*.label' => 'required|string|max:120',
            'additional_charges.*.type' => 'required|in:fixed,percentage',
            'additional_charges.*.value' => 'required|numeric|gt:0',
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
            'promo_coupon_code'        => 'nullable|string|max:50',
            'promo_manual_type'        => 'nullable|in:percentage,fixed',
            'promo_manual_value'       => 'nullable|numeric|min:0',
            'promo_manual_label'       => 'nullable|string|max:255',
            'notes'                    => 'nullable|string',
        ], [
            'additional_charges.*.label.required' => 'Each additional charge needs a label or description.',
            'additional_charges.*.type.required' => 'Select a charge type for each additional charge.',
            'additional_charges.*.value.required' => 'Enter a value for each additional charge.',
            'additional_charges.*.value.gt' => 'Additional charge values must be greater than zero.',
        ])->validate();

        $this->validateAdditionalChargeRows($data['additional_charges'] ?? []);

        if (!$user->isAdmin() && (int) $data['branch_id'] !== (int) $user->branch_id) {
            abort(403);
        }

        $branchId = (int) $data['branch_id'];
        $branch = Branch::findOrFail($branchId);
        $data = $this->sanitizeDiscountDetailsForBranch($data, $branch);

        $createdOrder = DB::transaction(function () use ($data, $branch, $user) {
            $prepared = $this->prepareOrderComputation($data, $branch);
            $lockedStocks = $this->lockAndValidateInventoryForItems($data['items'], $prepared['menus_keyed'], $branch->id);

            $order = MenuOrder::create([
                'branch_id' => $branch->id,
                'customer_name' => trim((string) ($data['customer_name'] ?? '')) ?: null,
                'subtotal' => $prepared['subtotal'],
                'additional_charge_label' => $prepared['additional_charge_label'],
                'additional_charge_amount' => $prepared['additional_charge_amount'],
                'additional_charges' => $prepared['additional_charges'],
                'regular_pax' => $prepared['regular_pax'],
                'pwd_pax' => $prepared['pwd_pax'],
                'senior_pax' => $prepared['senior_pax'],
                'total_pax' => $prepared['total_pax'],
                'discount_type'       => $prepared['discount_type'],
                'discount_id_number'  => $this->buildDiscountJson($data, 'ids'),
                'discount_name'       => $this->buildDiscountJson($data, 'names'),
                'discount_amount' => $prepared['totals']['discount_amount'],
                'promo_campaign_id' => $prepared['promo']['campaign_id'],
                'promo_discount_source' => $prepared['promo']['source'],
                'promo_discount_code' => $prepared['promo']['code'],
                'promo_discount_label' => $prepared['promo']['label'],
                'promo_discount_amount' => $prepared['totals']['promo_discount_amount'],
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
            $this->deductInventoryForOrder($order, $savedItems, $prepared['menus_keyed'], $lockedStocks);
            $this->recordPromoRedemption($order, $prepared['promo'], $user);

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

        $menus = Menu::with('items')
            ->where('branch_id', $menuOrder->branch_id)
            ->orderBy('name')
            ->get();

        return view('menu-orders.show', compact('menuOrder', 'menus'));
    }

    public function edit(MenuOrder $menuOrder): View
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ($this->itemsLockedByPayments($menuOrder)) {
            abort(403, 'Orders with payments can no longer be edited.');
        }

        $menuOrder->load(['branch', 'items.menu', 'table', 'payments']);
        $user = auth()->user();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $selectedBranchId = $menuOrder->branch_id;

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

        $menus = Menu::with('items')
            ->where('branch_id', $menuOrder->branch_id)
            ->orderBy('name')
            ->get();

        return view('menu-orders.edit', compact('branches', 'selectedBranchId', 'menuOrder', 'tables', 'menus'));
    }

    public function update(Request $request, MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ($this->itemsLockedByPayments($menuOrder)) {
            return back()->with('error', 'Orders with payments can no longer be edited.');
        }

        $payload = $request->all();
        $payload['additional_charges'] = $this->normalizeAdditionalChargeInput(
            $request->input('additional_charges', []),
            $request->input('additional_charge_label'),
            $request->input('additional_charge_amount')
        );

        $data = validator($payload, [
            'branch_id'                => 'required|exists:branches,id',
            'table_id'                 => 'nullable|exists:tables,id',
            'customer_name'            => 'nullable|string|max:255',
            'additional_charges'       => 'nullable|array',
            'additional_charges.*.label' => 'required|string|max:120',
            'additional_charges.*.type' => 'required|in:fixed,percentage',
            'additional_charges.*.value' => 'required|numeric|gt:0',
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
            'promo_coupon_code'        => 'nullable|string|max:50',
            'promo_manual_type'        => 'nullable|in:percentage,fixed',
            'promo_manual_value'       => 'nullable|numeric|min:0',
            'promo_manual_label'       => 'nullable|string|max:255',
            'notes'                    => 'nullable|string',
        ], [
            'additional_charges.*.label.required' => 'Each additional charge needs a label or description.',
            'additional_charges.*.type.required' => 'Select a charge type for each additional charge.',
            'additional_charges.*.value.required' => 'Enter a value for each additional charge.',
            'additional_charges.*.value.gt' => 'Additional charge values must be greater than zero.',
        ])->validate();

        $this->validateAdditionalChargeRows($data['additional_charges'] ?? []);

        $branch = Branch::findOrFail((int) $data['branch_id']);
        if ((int) $branch->id !== (int) $menuOrder->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'The branch of an existing menu order cannot be changed.',
            ]);
        }

        $data = $this->sanitizeDiscountDetailsForBranch($data, $branch);

        DB::transaction(function () use ($menuOrder, $data, $branch, $request) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();
            $this->releasePromoRedemption($lockedOrder);
            $prepared = $this->prepareOrderDetailsComputation($lockedOrder, $data, $branch);

            $currentTable = DiningTable::where('current_order_id', $lockedOrder->id)->lockForUpdate()->first();
            $requestedTableId = isset($data['table_id']) ? (int) $data['table_id'] : null;

            if ($currentTable && $currentTable->id !== $requestedTableId) {
                $currentTable->update(['current_order_id' => null, 'status' => 'available']);
            }

            $lockedOrder->update([
                'branch_id' => $branch->id,
                'customer_name' => trim((string) ($data['customer_name'] ?? '')) ?: null,
                'subtotal' => $prepared['subtotal'],
                'additional_charge_label' => $prepared['additional_charge_label'],
                'additional_charge_amount' => $prepared['additional_charge_amount'],
                'additional_charges' => $prepared['additional_charges'],
                'regular_pax' => $prepared['regular_pax'],
                'pwd_pax' => $prepared['pwd_pax'],
                'senior_pax' => $prepared['senior_pax'],
                'total_pax' => $prepared['total_pax'],
                'discount_type'       => $prepared['discount_type'],
                'discount_id_number'  => $this->buildDiscountJson($data, 'ids'),
                'discount_name'       => $this->buildDiscountJson($data, 'names'),
                'discount_amount' => $prepared['totals']['discount_amount'],
                'promo_campaign_id' => $prepared['promo']['campaign_id'],
                'promo_discount_source' => $prepared['promo']['source'],
                'promo_discount_code' => $prepared['promo']['code'],
                'promo_discount_label' => $prepared['promo']['label'],
                'promo_discount_amount' => $prepared['totals']['promo_discount_amount'],
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

            $this->recordPromoRedemption($lockedOrder, $prepared['promo'], $request->user());

            if ($requestedTableId && (!$currentTable || $currentTable->id !== $requestedTableId)) {
                $this->assignTableToOrder($lockedOrder, $requestedTableId);
            }
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Menu order updated successfully.');
    }

    public function storeItem(Request $request, MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ((string) $menuOrder->status !== 'open') {
            return back()->with('error', 'Items can only be added to open orders.');
        }

        if ($this->itemsLockedByPayments($menuOrder)) {
            return back()->with('error', 'Cannot add order items after payments have been recorded.');
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1|max:999',
        ]);

        DB::transaction(function () use ($menuOrder, $data) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();
            $branch = Branch::findOrFail($lockedOrder->branch_id);
            $prepared = $this->prepareOrderComputation($data, $branch, resolvePromo: false);
            $lockedStocks = $this->lockAndValidateInventoryForItems($data['items'], $prepared['menus_keyed'], $branch->id);

            $savedItems = $lockedOrder->items()->createMany($prepared['items_payload']);
            $this->deductInventoryForOrder($lockedOrder, $savedItems, $prepared['menus_keyed'], $lockedStocks);
            $this->refreshOrderTotalsFromCurrentItems($lockedOrder);
        });

        return $this->redirectToOrder($request, $menuOrder)->with('success', 'Menu order items added and inventory deducted.');
    }

    public function updateItemQuantity(Request $request, MenuOrder $menuOrder, MenuOrderItem $item): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ((int) $item->menu_order_id !== (int) $menuOrder->id) {
            abort(404);
        }

        if ((string) $menuOrder->status !== 'open') {
            return back()->with('error', 'Items can only be edited on open orders.');
        }

        if ($this->itemsLockedByPayments($menuOrder)) {
            return back()->with('error', 'Cannot edit an order item after payments have been recorded.');
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        DB::transaction(function () use ($menuOrder, $item, $data) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();
            $lockedItem = MenuOrderItem::whereKey($item->id)
                ->where('menu_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedItem->loadMissing('menu.items');

            $newQuantity = (int) $data['quantity'];
            $oldQuantity = (int) $lockedItem->quantity;

            if ($newQuantity === $oldQuantity) {
                return;
            }

            $menu = $lockedItem->menu;
            $delta = $newQuantity - $oldQuantity;

            if ($lockedItem->inventory_deducted && $menu && $menu->items->isNotEmpty()) {
                if ($delta > 0) {
                    $this->deductQuantityDeltaForMenu($lockedOrder, $menu, $delta);
                } else {
                    $this->replenishQuantityDeltaForMenu($lockedOrder, $menu, abs($delta));
                }
            }

            $unitPrice = round((float) $lockedItem->unit_price, 2);
            $unitCost = $menu ? round((float) $menu->computeUnitCost(), 2) : 0.0;
            $subtotal = round($unitPrice * $newQuantity, 2);
            $cost = round($unitCost * $newQuantity, 2);

            $lockedItem->update([
                'quantity' => $newQuantity,
                'subtotal' => $subtotal,
                'cost' => $cost,
                'profit' => round($subtotal - $cost, 2),
            ]);

            $this->refreshOrderTotalsFromCurrentItems($lockedOrder);
        });

        return $this->redirectToOrder($request, $menuOrder)->with('success', 'Item quantity updated.');
    }

    public function destroyItem(Request $request, MenuOrder $menuOrder, MenuOrderItem $item): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if ((int) $item->menu_order_id !== (int) $menuOrder->id) {
            abort(404);
        }

        if ($this->itemsLockedByPayments($menuOrder)) {
            return back()->with('error', 'Cannot delete an order item after payments have been recorded.');
        }

        DB::transaction(function () use ($menuOrder, $item) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();
            $lockedItem = MenuOrderItem::whereKey($item->id)
                ->where('menu_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->items()->count() <= 1) {
                throw ValidationException::withMessages([
                    'items' => 'An order must keep at least one menu item.',
                ]);
            }

            $this->replenishInventoryForOrderItem($lockedOrder, $lockedItem, 'Deleted Menu Order Item', 'Auto-replenished because an Admin deleted this order item.');
            $lockedItem->delete();
            $this->refreshOrderTotalsFromCurrentItems($lockedOrder);
        });

        return $this->redirectToOrder($request, $menuOrder)->with('success', 'Menu order item deleted and inventory replenished.');
    }

    /**
     * Item add/edit/remove actions are triggered from both the order's show page
     * and its edit page — send the user back to wherever they started instead of
     * always bouncing to show.
     */
    private function redirectToOrder(Request $request, MenuOrder $menuOrder): RedirectResponse
    {
        return $request->input('return_to') === 'edit'
            ? redirect()->route('menu-orders.edit', $menuOrder)
            : redirect()->route('menu-orders.show', $menuOrder);
    }

    /**
     * Deduct ingredients for an incremental quantity increase on an existing order
     * item, locking and validating stock for just the delta (not the full line).
     */
    private function deductQuantityDeltaForMenu(MenuOrder $order, Menu $menu, int $deltaQuantity): void
    {
        $userId = auth()->id();
        $now = now();

        $ingredientIds = $menu->items->pluck('id')->all();
        $stocks = Item::whereIn('id', $ingredientIds)
            ->where('branch_id', $order->branch_id)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($menu->items as $ingredient) {
            $needed = round((float) $ingredient->pivot->quantity_required * $deltaQuantity, 4);
            if ($needed <= 0) {
                continue;
            }

            $stock = $stocks->get($ingredient->id);
            $available = $stock ? (float) $stock->quantity : 0.0;

            if (! $stock || $available < $needed) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock for ingredient "'.($ingredient->name ?? 'Unknown ingredient').'" to increase this item\'s quantity.',
                ]);
            }
        }

        foreach ($menu->items as $ingredient) {
            $needed = round((float) $ingredient->pivot->quantity_required * $deltaQuantity, 4);
            if ($needed <= 0) {
                continue;
            }

            $stock = $stocks->get($ingredient->id);
            $beginning = (float) $stock->quantity;
            $this->inventoryService->decrease($stock, $needed);
            $remaining = (float) $stock->quantity;

            InventoryTransaction::create([
                'item_id' => $stock->id,
                'branch_id' => $order->branch_id,
                'type' => 'out',
                'quantity' => $needed,
                'beginning_quantity' => $beginning,
                'remaining_quantity' => $remaining,
                'transaction_price' => $stock->unit_price ? (float) $stock->unit_price * $needed : null,
                'transaction_date' => $now,
                'reason' => 'Sale — '.$order->orderNumber().' (quantity increased)',
                'status' => 'approved',
                'notes' => 'Auto-deducted for a menu order item quantity increase.',
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * Replenish ingredients for an incremental quantity decrease on an existing
     * order item.
     */
    private function replenishQuantityDeltaForMenu(MenuOrder $order, Menu $menu, int $deltaQuantity): void
    {
        $userId = auth()->id();
        $now = now();

        foreach ($menu->items as $ingredient) {
            $replenishQty = round((float) $ingredient->pivot->quantity_required * $deltaQuantity, 4);
            if ($replenishQty <= 0) {
                continue;
            }

            $stock = Item::whereKey($ingredient->id)
                ->where('branch_id', $order->branch_id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $beginning = (float) $stock->quantity;
            $this->inventoryService->increase($stock, $replenishQty);
            $remaining = (float) $stock->quantity;

            InventoryTransaction::create([
                'item_id' => $stock->id,
                'branch_id' => $order->branch_id,
                'type' => 'in',
                'quantity' => $replenishQty,
                'beginning_quantity' => $beginning,
                'remaining_quantity' => $remaining,
                'transaction_price' => $stock->unit_price ? (float) $stock->unit_price * $replenishQty : null,
                'transaction_date' => $now,
                'reason' => 'Menu Order Item Quantity Decreased',
                'status' => 'approved',
                'notes' => 'Auto-replenished because the order item quantity was reduced.',
                'created_by' => $userId,
            ]);
        }
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
            'amount_tendered'  => 'required|numeric|min:0.01|gte:amount',
            'method'           => 'required|in:cash,gcash,card,bank',
            'payment_date'     => 'required|date|before_or_equal:now',
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

            $activeShift = CashShift::where('cashier_id', $user->id)
                ->where('branch_id', $lockedOrder->branch_id)
                ->where('status', 'open')
                ->first();

            $payment = MenuOrderPayment::create([
                'branch_id'               => $lockedOrder->branch_id,
                'menu_order_id'           => $lockedOrder->id,
                'cash_shift_id'           => $activeShift?->id,
                'pos_terminal_id'         => $activeShift?->pos_terminal_id,
                'amount'                  => $applied,
                'amount_tendered'         => $tendered,
                'change_amount'           => $change,
                'subtotal'                => round((float) $lockedOrder->subtotal, 2),
                'additional_charge_label' => $lockedOrder->additional_charge_label,
                'additional_charge_amount'=> round((float) ($lockedOrder->additional_charge_amount ?? 0), 2),
                'additional_charges'      => $lockedOrder->additionalChargesList(),
                'total_vat_exempt'        => round((float) $lockedOrder->total_vat_exempt, 2),
                'total_discount'          => round((float) $lockedOrder->discount_amount, 2),
                'final_total'             => round((float) $lockedOrder->total_amount, 2),
                'discount_type'           => $lockedOrder->discount_type,
                'discount_amount'         => round((float) $lockedOrder->discount_amount, 2),
                'promo_campaign_id'       => $lockedOrder->promo_campaign_id,
                'promo_discount_source'   => $lockedOrder->promo_discount_source,
                'promo_discount_code'     => $lockedOrder->promo_discount_code,
                'promo_discount_label'    => $lockedOrder->promo_discount_label,
                'promo_discount_amount'   => round((float) $lockedOrder->promo_discount_amount, 2),
                'vat_amount'              => round((float) $lockedOrder->vat_amount, 2),
                'is_vat_exempt'           => round((float) $lockedOrder->total_vat_exempt, 2) > 0,
                'method'                  => $data['method'],
                'reference_number'        => $data['reference_number'] ?? null,
                'payment_date'            => $data['payment_date'],
                'notes'                   => $data['notes'] ?? null,
                'received_by'             => $user->id,
            ]);

            // Derive the OR number from the payment's own ID so concurrent
            // payments can never produce duplicate numbers.
            $payment->update([
                'or_number' => 'OR-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
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

            $this->releasePromoRedemption($lockedOrder);
            $this->releaseTableForOrder($lockedOrder);
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Order cancelled.');
    }

    public function void(Request $request, MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if (!auth()->user()->isBranchManager()) {
            abort(403, 'Only Branch Managers can void orders.');
        }

        if ((string) $menuOrder->status === 'voided') {
            return back()->with('error', 'This order is already voided.');
        }

        if ((string) $menuOrder->status === 'cancelled') {
            return back()->with('error', 'Cannot void a cancelled order.');
        }

        if ($menuOrder->payments()->exists()) {
            return back()->with('error', 'Cannot void an order with recorded payments.');
        }

        $request->validate([
            'void_reason' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($menuOrder, $request) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();
            
            if ((string) $lockedOrder->status === 'voided') {
                return;
            }

            $this->replenishInventoryForOrderItems($lockedOrder, 'Voided Menu Order', 'Void Reason: ' . $request->input('void_reason'));

            $lockedOrder->update([
                'status' => 'voided',
                'payment_status' => 'unpaid',
                'amount_paid' => 0,
                'balance' => round((float) $lockedOrder->total_amount, 2),
                'void_reason' => $request->input('void_reason'),
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);

            $this->releasePromoRedemption($lockedOrder);
            $this->releaseTableForOrder($lockedOrder);
        });

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', 'Order has been voided.');
    }

    public function reactivate(MenuOrder $menuOrder): RedirectResponse
    {
        $this->authorizeBranch($menuOrder->branch_id);

        if (!auth()->user()->isAdmin()) {
            abort(403, 'Only Admins can reactivate an order.');
        }

        $reactivatableStatuses = ['cancelled', 'voided', 'completed'];

        if (!in_array((string) $menuOrder->status, $reactivatableStatuses, true)) {
            return back()->with('error', 'Only cancelled, voided, or completed orders can be reactivated.');
        }

        $wasCompleted = (string) $menuOrder->status === 'completed';

        DB::transaction(function () use ($menuOrder, $reactivatableStatuses, $wasCompleted) {
            $lockedOrder = MenuOrder::whereKey($menuOrder->id)->lockForUpdate()->firstOrFail();

            if (!in_array((string) $lockedOrder->status, $reactivatableStatuses, true)) {
                return;
            }

            $updates = [
                'status' => 'open',
                'is_reactivated' => true,
            ];

            // Cancelled/voided orders always have zero payments (enforced when
            // they were cancelled/voided), so it's safe to zero these out. A
            // completed order already has real payments recorded — leave its
            // payment_status/amount_paid/balance alone so paid money stays paid.
            if (!$wasCompleted) {
                $updates['payment_status'] = 'unpaid';
                $updates['void_reason'] = null;
                $updates['voided_by'] = null;
                $updates['voided_at'] = null;
            }

            $lockedOrder->update($updates);
        });

        $message = $wasCompleted
            ? 'Order reopened. Items and payments can be added again since it was previously completed.'
            : 'Order reactivated. Note: table assignment, promo redemption, and inventory are not automatically restored — re-apply them manually if needed.';

        return redirect()->route('menu-orders.show', $menuOrder)->with('success', $message);
    }

    /**
     * A payment can no longer be edited/deleted once the business day it
     * belongs to has been closed with a locked Z Reading — that reading is
     * an immutable snapshot, and letting the underlying payment drift out
     * from under it would silently desynchronize the audit trail.
     */
    private function paymentLockedByZReading(MenuOrderPayment $payment): bool
    {
        if (!$payment->pos_terminal_id || !$payment->payment_date) {
            return false;
        }

        return ZReading::where('pos_terminal_id', $payment->pos_terminal_id)
            ->where('business_date', $payment->payment_date->toDateString())
            ->where('status', 'locked')
            ->exists();
    }

    private function refreshOrderAfterPaymentChange(MenuOrder $order): void
    {
        $order->refresh();
        $amountPaid = round((float) $order->payments()->sum('amount'), 2);
        $balance = round(max(0, (float) $order->total_amount - $amountPaid), 2);

        $updates = [
            'amount_paid' => $amountPaid,
            'balance' => $balance,
            'payment_status' => $balance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
        ];

        // Only ever flip between open/completed here — never touch a
        // cancelled/voided order (those can't have payments in the first place).
        if (in_array((string) $order->status, ['open', 'completed'], true)) {
            $updates['status'] = $balance <= 0 ? 'completed' : 'open';
        }

        $order->update($updates);
    }

    public function updatePayment(Request $request, MenuOrderPayment $payment): RedirectResponse
    {
        $this->authorizeBranch($payment->branch_id);

        if (!auth()->user()->isAdmin()) {
            abort(403, 'Only Admins can edit a payment.');
        }

        if ($this->paymentLockedByZReading($payment)) {
            return back()->with('error', 'This payment belongs to a business day that already has a locked Z Reading and can no longer be edited. Void that Z Reading first if a correction is truly needed.');
        }

        $data = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'amount_tendered'  => 'required|numeric|min:0.01|gte:amount',
            'method'           => 'required|in:cash,gcash,card,bank',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        DB::transaction(function () use ($payment, $data) {
            $order = MenuOrder::whereKey($payment->menu_order_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = MenuOrderPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $otherPaymentsTotal = round((float) $order->payments()->where('id', '!=', $payment->id)->sum('amount'), 2);
            $newAmount = round((float) $data['amount'], 2);

            if ($otherPaymentsTotal + $newAmount > round((float) $order->total_amount, 2) + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'This amount would make total payments exceed the order total.',
                ]);
            }

            $tendered = round((float) $data['amount_tendered'], 2);

            $lockedPayment->update([
                'amount' => $newAmount,
                'amount_tendered' => $tendered,
                'change_amount' => max(0, round($tendered - $newAmount, 2)),
                'method' => $data['method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->refreshOrderAfterPaymentChange($order);
        });

        return back()->with('success', 'Payment updated.');
    }

    public function destroyPayment(MenuOrderPayment $payment): RedirectResponse
    {
        $this->authorizeBranch($payment->branch_id);

        if (!auth()->user()->isAdmin()) {
            abort(403, 'Only Admins can delete a payment.');
        }

        if ($this->paymentLockedByZReading($payment)) {
            return back()->with('error', 'This payment belongs to a business day that already has a locked Z Reading and can no longer be deleted. Void that Z Reading first if a correction is truly needed.');
        }

        DB::transaction(function () use ($payment) {
            $order = MenuOrder::whereKey($payment->menu_order_id)->lockForUpdate()->firstOrFail();
            MenuOrderPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail()->delete();
            $this->refreshOrderAfterPaymentChange($order);
        });

        return back()->with('success', 'Payment deleted.');
    }

    public function paymentReceipt(MenuOrderPayment $payment): View
    {
        $this->authorizeBranch($payment->branch_id);
        $payment->load(['order.branch', 'order.items.menu', 'receivedBy']);

        return view('menu-orders.receipt', compact('payment'));
    }

    public function billingReceipt(MenuOrder $menuOrder): View
    {
        $this->authorizeBranch($menuOrder->branch_id);
        $menuOrder->load(['branch', 'items.menu', 'payments']);

        return view('menu-orders.billing', compact('menuOrder'));
    }

    /**
     * Deduct inventory ingredients for every ordered item.
     * Skips items whose ingredients are already deducted or have no recipe.
     */
    private function deductInventoryForOrder(MenuOrder $order, $savedItems, $menusKeyed, $lockedStocks): void
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

            foreach ($menu->items as $ingredient) {
                $needed = round((float) $ingredient->pivot->quantity_required * $orderItem->quantity, 4);
                if ($needed <= 0) {
                    continue;
                }

                // Lock the inventory row to prevent race conditions
                $stock = $lockedStocks->get($ingredient->id);

                if (! $stock) {
                    // Insufficient stock — skip deduction for this ingredient
                    throw ValidationException::withMessages([
                        'items' => 'Unable to deduct inventory for "' . ($ingredient->name ?? 'Unknown ingredient') . '".',
                    ]);
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
                    'reason'             => 'Sale — ' . $order->orderNumber(),
                    'status'             => 'approved',
                    'notes'              => 'Auto-deducted from menu order.',
                    'created_by'         => $userId,
                ]);
            }

            $orderItem->update(['inventory_deducted' => true]);
        }
    }

    private function replenishInventoryForOrderItems(MenuOrder $order, string $reason, string $notes): void
    {
        $userId = auth()->id();
        $now    = now();

        $itemsToReplenish = $order->items()->with('menu.items')->where('inventory_deducted', true)->get();

        foreach ($itemsToReplenish as $orderItem) {
            $menu = $orderItem->menu;
            if (! $menu || $menu->items->isEmpty()) {
                continue;
            }

            foreach ($menu->items as $ingredient) {
                $replenishQty = round((float) $ingredient->pivot->quantity_required * $orderItem->quantity, 4);
                if ($replenishQty <= 0) {
                    continue;
                }

                $stock = Item::whereKey($ingredient->id)
                    ->where('branch_id', $order->branch_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    continue; // Stock might have been deleted, skip
                }

                $beginning = (float) $stock->quantity;
                $this->inventoryService->increase($stock, $replenishQty);
                $remaining = (float) $stock->quantity;

                InventoryTransaction::create([
                    'item_id'            => $stock->id,
                    'branch_id'          => $order->branch_id,
                    'type'               => 'in',
                    'quantity'           => $replenishQty,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price'  => $stock->unit_price ? (float) $stock->unit_price * $replenishQty : null,
                    'transaction_date'   => $now,
                    'reason'             => $reason,
                    'status'             => 'approved',
                    'notes'              => $notes,
                    'created_by'         => $userId,
                ]);
            }
            
            $orderItem->update(['inventory_deducted' => false]);
        }
    }

    private function replenishInventoryForOrderItem(MenuOrder $order, MenuOrderItem $orderItem, string $reason, string $notes): void
    {
        if (! $orderItem->inventory_deducted) {
            return;
        }

        $userId = auth()->id();
        $now    = now();
        $orderItem->loadMissing('menu.items');
        $menu = $orderItem->menu;

        if (! $menu || $menu->items->isEmpty()) {
            $orderItem->update(['inventory_deducted' => false]);
            return;
        }

        foreach ($menu->items as $ingredient) {
            $replenishQty = round((float) $ingredient->pivot->quantity_required * $orderItem->quantity, 4);
            if ($replenishQty <= 0) {
                continue;
            }

            $stock = Item::whereKey($ingredient->id)
                ->where('branch_id', $order->branch_id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $beginning = (float) $stock->quantity;
            $this->inventoryService->increase($stock, $replenishQty);
            $remaining = (float) $stock->quantity;

            InventoryTransaction::create([
                'item_id'            => $stock->id,
                'branch_id'          => $order->branch_id,
                'type'               => 'in',
                'quantity'           => $replenishQty,
                'beginning_quantity' => $beginning,
                'remaining_quantity' => $remaining,
                'transaction_price'  => $stock->unit_price ? (float) $stock->unit_price * $replenishQty : null,
                'transaction_date'   => $now,
                'reason'             => $reason,
                'status'             => 'approved',
                'notes'              => $notes,
                'created_by'         => $userId,
            ]);
        }

        $orderItem->update(['inventory_deducted' => false]);
    }

    private function refreshOrderTotalsFromCurrentItems(MenuOrder $order): void
    {
        $order->load('items');
        $branch = Branch::findOrFail($order->branch_id);
        $subtotal = round((float) $order->items->sum('subtotal'), 2);
        $chargeComputation = $this->calculateAdditionalCharges($order->additionalChargesList(), $subtotal);
        $additionalChargeAmount = $chargeComputation['total'];
        $totalPax = max(1, (int) ($order->total_pax ?? 1));
        $discountedPax = max(0, (int) ($order->pwd_pax ?? 0) + (int) ($order->senior_pax ?? 0));
        $gross = round($subtotal + $additionalChargeAmount, 2);
        $promoDiscountAmount = $this->recomputeStoredPromoAmount($order, $gross);

        $totals = $this->computeTotals(
            $branch,
            $subtotal,
            $additionalChargeAmount,
            $totalPax,
            $discountedPax,
            $this->branchUsesVat($branch),
            $promoDiscountAmount
        );

        $amountPaid = round((float) $order->payments()->sum('amount'), 2);
        $balance = round(max(0, $totals['total_amount'] - $amountPaid), 2);

        $order->update([
            'subtotal' => $subtotal,
            'additional_charge_label' => $chargeComputation['legacy_label'],
            'additional_charge_amount' => $additionalChargeAmount,
            'additional_charges' => $chargeComputation['charges'],
            'discount_amount' => $totals['discount_amount'],
            'promo_discount_amount' => $totals['promo_discount_amount'],
            'total_vat_exempt' => $totals['total_vat_exempt'],
            'vat_rate' => $totals['vat_rate'],
            'vat_amount' => $totals['vat_amount'],
            'total_amount' => $totals['total_amount'],
            'amount_paid' => $amountPaid,
            'balance' => $balance,
            'payment_status' => $balance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
        ]);
    }

    private function prepareOrderDetailsComputation(MenuOrder $order, array $data, Branch $branch): array
    {
        $subtotal = round((float) $order->items()->sum('subtotal'), 2);
        $chargeComputation = $this->calculateAdditionalCharges($data['additional_charges'] ?? [], $subtotal);

        $regularPax = max(0, (int) ($data['regular_pax'] ?? 0));
        $pwdPax = $this->branchAllowsPwdDiscount($branch) ? max(0, (int) ($data['pwd_pax'] ?? 0)) : 0;
        $seniorPax = $this->branchAllowsSeniorDiscount($branch) ? max(0, (int) ($data['senior_pax'] ?? 0)) : 0;
        $totalPax = $regularPax + $pwdPax + $seniorPax;
        if ($totalPax <= 0) {
            $regularPax = 1;
            $totalPax = 1;
        }

        $discountedPax = $pwdPax + $seniorPax;
        $discountType = 'none';
        if ($pwdPax > 0 && $seniorPax > 0) {
            $discountType = 'mixed';
        } elseif ($pwdPax > 0) {
            $discountType = 'pwd';
        } elseif ($seniorPax > 0) {
            $discountType = 'senior';
        }

        $gross = round($subtotal + $chargeComputation['total'], 2);
        $promo = $this->resolvePromoDiscount($data, $branch, $gross);

        return [
            'subtotal' => $subtotal,
            'additional_charge_amount' => $chargeComputation['total'],
            'additional_charge_label' => $chargeComputation['legacy_label'],
            'additional_charges' => $chargeComputation['charges'],
            'regular_pax' => $regularPax,
            'pwd_pax' => $pwdPax,
            'senior_pax' => $seniorPax,
            'total_pax' => $totalPax,
            'discount_type' => $discountType,
            'promo' => $promo,
            'totals' => $this->computeTotals(
                $branch,
                $subtotal,
                $chargeComputation['total'],
                $totalPax,
                $discountedPax,
                $this->branchUsesVat($branch),
                $promo['amount']
            ),
        ];
    }

    private function prepareOrderComputation(array $data, Branch $branch, bool $resolvePromo = true): array
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
        $chargeComputation = $this->calculateAdditionalCharges($data['additional_charges'] ?? [], $subtotal);
        $additionalChargeAmount = $chargeComputation['total'];
        $additionalChargeLabel = $chargeComputation['legacy_label'];

        $regularPax = max(0, (int) ($data['regular_pax'] ?? 0));
        $pwdPax = $this->branchAllowsPwdDiscount($branch) ? max(0, (int) ($data['pwd_pax'] ?? 0)) : 0;
        $seniorPax = $this->branchAllowsSeniorDiscount($branch) ? max(0, (int) ($data['senior_pax'] ?? 0)) : 0;
        $totalPax = $regularPax + $pwdPax + $seniorPax;
        if ($totalPax <= 0) {
            $regularPax = 1;
            $totalPax = 1;
        }

        $vatEnabled = $this->branchUsesVat($branch);
        $discountedPax = $pwdPax + $seniorPax;

        $discountType = 'none';
        if ($pwdPax > 0 && $seniorPax > 0) {
            $discountType = 'mixed';
        } elseif ($pwdPax > 0) {
            $discountType = 'pwd';
        } elseif ($seniorPax > 0) {
            $discountType = 'senior';
        }

        $gross = round($subtotal + $additionalChargeAmount, 2);
        $promo = $resolvePromo
            ? $this->resolvePromoDiscount($data, $branch, $gross)
            : ['campaign_id' => null, 'source' => null, 'code' => null, 'label' => null, 'type' => null, 'value' => null, 'amount' => 0.0];

        $totals = $this->computeTotals(
            $branch, $subtotal, $additionalChargeAmount,
            $totalPax, $discountedPax, $vatEnabled, $promo['amount']
        );

        return [
            'items_payload'           => $itemsPayload,
            'menus_keyed'             => $menus,
            'subtotal'                => $subtotal,
            'additional_charge_amount'=> $additionalChargeAmount,
            'additional_charge_label' => $additionalChargeLabel,
            'additional_charges'      => $chargeComputation['charges'],
            'regular_pax'             => $regularPax,
            'pwd_pax'                 => $pwdPax,
            'senior_pax'              => $seniorPax,
            'total_pax'               => $totalPax,
            'discount_type'           => $discountType,
            'promo'                   => $promo,
            'totals'                  => $totals,
        ];
    }

    private function computeTotals(
        Branch $branch, float $subtotal, float $additionalChargeAmount,
        int $totalPax, int $discountedPax, bool $vatEnabled,
        float $promoDiscountAmount = 0.0
    ): array {
        $vatRate = $vatEnabled ? (float) ($branch->vat_percentage ?? 12.00) : 0.0;
        $gross = round($subtotal + $additionalChargeAmount, 2);

        // Promotional discounts are ordinary commercial discounts (not
        // government-mandated), so they come off the price first and stay
        // fully VATable — the PWD/Senior 20% below is computed on what's
        // left, and only *that* portion is VAT-exempt.
        $promoDiscountAmount = round(min(max(0, $promoDiscountAmount), $gross), 2);
        $netAfterPromo = round(max(0, $gross - $promoDiscountAmount), 2);

        $perPaxGross = $netAfterPromo / max(1, $totalPax);
        $discountBase = $perPaxGross * $discountedPax;
        $discountAmount = round($discountBase * 0.20, 2);
        $totalVatExempt = $vatEnabled ? round($discountBase, 2) : 0.0;

        $vatAmount = 0.0;
        if ($vatEnabled) {
            $vatableGross = round(max(0, $netAfterPromo - $totalVatExempt), 2);
            $vatAmount = $vatRate > 0 ? round($vatableGross * ($vatRate / (100 + $vatRate)), 2) : 0.0;
        }

        $totalAmount = round(max(0, $netAfterPromo - $discountAmount), 2);

        return [
            'vat_rate' => $vatRate,
            'discount_amount' => $discountAmount,
            'total_vat_exempt' => $totalVatExempt,
            'vat_amount' => $vatAmount,
            'promo_discount_amount' => $promoDiscountAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function branchUsesVat(Branch $branch): bool
    {
        return (bool) ($branch->vat_enabled ?? true);
    }

    private function branchAllowsPwdDiscount(Branch $branch): bool
    {
        return (bool) ($branch->pwd_discount_enabled ?? true);
    }

    private function branchAllowsSeniorDiscount(Branch $branch): bool
    {
        return (bool) ($branch->senior_discount_enabled ?? true);
    }

    /**
     * Resolves the promotional discount to apply to a fresh gross total —
     * manual (staff-entered) discounts take precedence, then an explicitly
     * typed coupon code, then the best-matching automatic campaign. This is
     * entirely independent of PWD/Senior, which never passes through here.
     *
     * @return array{campaign_id: ?int, source: ?string, code: ?string, label: ?string, type: ?string, value: ?float, amount: float}
     */
    private function resolvePromoDiscount(array $data, Branch $branch, float $gross): array
    {
        $manualValue = $data['promo_manual_value'] ?? null;

        if ($manualValue !== null && $manualValue !== '' && (float) $manualValue > 0) {
            $user = auth()->user();
            if (!$user || (!$user->isAdmin() && !$user->isBranchManager())) {
                throw ValidationException::withMessages([
                    'promo_manual_value' => 'Only administrators or branch managers can apply a manual discount.',
                ]);
            }

            $type = in_array($data['promo_manual_type'] ?? '', ['percentage', 'fixed'], true)
                ? $data['promo_manual_type']
                : 'fixed';
            $value = round((float) $manualValue, 2);
            $amount = $type === 'percentage' ? ($gross * ($value / 100)) : $value;
            $amount = round(min(max(0, $amount), $gross), 2);

            return [
                'campaign_id' => null,
                'source' => 'manual',
                'code' => null,
                'label' => trim((string) ($data['promo_manual_label'] ?? '')) ?: 'Manual Discount',
                'type' => $type,
                'value' => $value,
                'amount' => $amount,
            ];
        }

        $code = trim((string) ($data['promo_coupon_code'] ?? ''));

        if ($code !== '') {
            $result = $this->discountCampaignService->validateCoupon($code, $branch->id, $gross);

            if (!$result['ok']) {
                throw ValidationException::withMessages([
                    'promo_coupon_code' => $result['message'] ?? 'This coupon code cannot be applied.',
                ]);
            }

            $campaign = $result['campaign'];

            return [
                'campaign_id' => $campaign->id,
                'source' => 'coupon',
                'code' => $campaign->code,
                'label' => $campaign->name,
                'type' => $campaign->type,
                'value' => (float) $campaign->value,
                'amount' => $result['amount'],
            ];
        }

        $auto = $this->discountCampaignService->findBestAutomaticCampaign($branch->id, $gross);

        if ($auto) {
            return [
                'campaign_id' => $auto->id,
                'source' => 'automatic',
                'code' => null,
                'label' => $auto->name,
                'type' => $auto->type,
                'value' => (float) $auto->value,
                'amount' => $auto->computeDiscountAmount($gross),
            ];
        }

        return ['campaign_id' => null, 'source' => null, 'code' => null, 'label' => null, 'type' => null, 'value' => null, 'amount' => 0.0];
    }

    /**
     * Recomputes just the *monetary amount* of an already-applied promo
     * discount against a new gross (e.g. after an item quantity changes).
     * Campaign-linked discounts rescale using the campaign's own rules;
     * manual discounts stay frozen at whatever amount staff entered.
     */
    private function recomputeStoredPromoAmount(MenuOrder $order, float $gross): float
    {
        if ($order->promo_discount_source === 'manual') {
            return round(min(max(0, (float) $order->promo_discount_amount), $gross), 2);
        }

        if ($order->promo_campaign_id) {
            $campaign = DiscountCampaign::find($order->promo_campaign_id);
            if ($campaign) {
                return $campaign->computeDiscountAmount($gross);
            }
        }

        return round(min(max(0, (float) $order->promo_discount_amount), $gross), 2);
    }

    /**
     * Logs the redemption for audit purposes and, for campaign-linked
     * discounts, atomically re-checks and consumes one usage slot inside
     * the caller's transaction (re-checking here — not just when the
     * discount was first resolved — closes the race where two concurrent
     * orders both resolve the same near-exhausted coupon).
     */
    private function recordPromoRedemption(MenuOrder $order, array $promo, $user): void
    {
        if (empty($promo['source'])) {
            return;
        }

        if ($promo['campaign_id']) {
            $campaign = DiscountCampaign::whereKey($promo['campaign_id'])->lockForUpdate()->first();

            if (!$campaign || !$campaign->is_active) {
                throw ValidationException::withMessages([
                    'promo_coupon_code' => 'This discount is no longer available.',
                ]);
            }

            if ($campaign->usage_limit !== null && $campaign->usage_count >= $campaign->usage_limit) {
                throw ValidationException::withMessages([
                    'promo_coupon_code' => 'This discount just reached its usage limit.',
                ]);
            }

            $campaign->increment('usage_count');
        }

        DiscountRedemption::create([
            'menu_order_id' => $order->id,
            'branch_id' => $order->branch_id,
            'discount_campaign_id' => $promo['campaign_id'],
            'source' => $promo['source'],
            'code_used' => $promo['code'],
            'label' => $promo['label'],
            'discount_type' => $promo['type'],
            'discount_value' => $promo['value'],
            'discount_amount' => (float) $order->promo_discount_amount,
            'applied_by' => $user->id,
            'status' => 'applied',
        ]);
    }

    /**
     * Reverses a previously-applied promo redemption: returns the usage
     * slot to its campaign (if any) and marks the audit log entry
     * released, rather than deleting it. Safe to call even if there's
     * nothing to release.
     */
    private function releasePromoRedemption(MenuOrder $order): void
    {
        $redemption = DiscountRedemption::where('menu_order_id', $order->id)
            ->where('status', 'applied')
            ->lockForUpdate()
            ->first();

        if (!$redemption) {
            return;
        }

        if ($redemption->discount_campaign_id) {
            DiscountCampaign::whereKey($redemption->discount_campaign_id)->lockForUpdate()->decrement('usage_count');
        }

        $redemption->update(['status' => 'released', 'released_at' => now()]);
    }

    private function lockAndValidateInventoryForItems(array $items, $menusKeyed, int $branchId)
    {
        $requirements = [];

        foreach ($items as $row) {
            $menu = $menusKeyed->get((int) $row['menu_id']);
            if (! $menu || $menu->items->isEmpty()) {
                continue;
            }

            $quantity = max(0, (int) ($row['quantity'] ?? 0));

            foreach ($menu->items as $ingredient) {
                $required = round((float) $ingredient->pivot->quantity_required * $quantity, 4);
                if ($required <= 0) {
                    continue;
                }

                $existing = $requirements[$ingredient->id] ?? [
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'required' => 0.0,
                ];

                $existing['required'] += $required;
                $requirements[$ingredient->id] = $existing;
            }
        }

        if (empty($requirements)) {
            return collect();
        }

        $stocks = Item::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', array_keys($requirements))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($requirements as $ingredientId => $requirement) {
            $stock = $stocks->get($ingredientId);
            $available = $stock ? (float) $stock->quantity : 0.0;
            $required = round((float) $requirement['required'], 4);

            if (! $stock || $available < $required) {
                $errors['items.stock_' . $ingredientId] = sprintf(
                    'Insufficient stock for ingredient "%s". Available: %s %s, required: %s %s.',
                    $requirement['name'] ?? 'Unknown ingredient',
                    number_format($available, 2),
                    $requirement['unit'] ?? '',
                    number_format($required, 2),
                    $requirement['unit'] ?? ''
                );
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $stocks;
    }

    private function normalizeAdditionalChargeInput(array $rows, ?string $legacyLabel = null, $legacyAmount = null): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $type = trim((string) ($row['type'] ?? ''));
            $value = $row['value'] ?? null;

            if ($label === '' && $type === '' && ($value === null || $value === '')) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'type' => $type,
                'value' => $value,
            ];
        }

        if (! empty($normalized)) {
            return $normalized;
        }

        $legacyAmount = round((float) ($legacyAmount ?? 0), 2);
        if ($legacyAmount <= 0) {
            return [];
        }

        return [[
            'label' => trim((string) ($legacyLabel ?: 'Additional Charge')),
            'type' => 'fixed',
            'value' => $legacyAmount,
        ]];
    }

    private function validateAdditionalChargeRows(array $charges): void
    {
        $errors = [];

        foreach ($charges as $index => $charge) {
            $type = (string) ($charge['type'] ?? '');
            $value = round((float) ($charge['value'] ?? 0), 2);

            if ($type === 'percentage' && $value > 100) {
                $errors["additional_charges.$index.value"] = 'Percentage-based additional charges cannot exceed 100%.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function calculateAdditionalCharges(array $charges, float $subtotal): array
    {
        $normalized = [];
        $total = 0.0;

        foreach ($charges as $charge) {
            $label = trim((string) ($charge['label'] ?? ''));
            $type = (string) ($charge['type'] ?? 'fixed');
            $value = round((float) ($charge['value'] ?? 0), 2);

            if ($label === '' || $value <= 0) {
                continue;
            }

            $amount = $type === 'percentage'
                ? round($subtotal * ($value / 100), 2)
                : round($value, 2);

            if ($amount <= 0) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'type' => $type === 'percentage' ? 'percentage' : 'fixed',
                'value' => $value,
                'amount' => $amount,
                'base_subtotal' => $type === 'percentage' ? round($subtotal, 2) : null,
            ];

            $total += $amount;
        }

        $normalized = array_values($normalized);
        $legacyLabel = null;

        if (count($normalized) === 1) {
            $legacyLabel = $normalized[0]['label'];
        } elseif (count($normalized) > 1) {
            $legacyLabel = 'Multiple Additional Charges';
        }

        return [
            'charges' => $normalized,
            'total' => round($total, 2),
            'legacy_label' => $legacyLabel,
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

    private function sanitizeDiscountDetailsForBranch(array $data, Branch $branch): array
    {
        if (! $this->branchAllowsPwdDiscount($branch)) {
            $data['pwd_pax'] = 0;
            $data['pwd_ids'] = [];
            $data['pwd_names'] = [];
        }

        if (! $this->branchAllowsSeniorDiscount($branch)) {
            $data['senior_pax'] = 0;
            $data['senior_ids'] = [];
            $data['senior_names'] = [];
        }

        return $data;
    }
}
