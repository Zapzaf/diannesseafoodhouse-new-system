<?php

namespace App\Http\Controllers;

use App\Exports\InventoryExport;
use App\Http\Requests\StoreItemRequest;
use App\Jobs\SendLowStockEmail;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\Transfer;
use App\Services\InventoryService;
use App\Support\InventoryQuantity;
use App\Support\InventoryUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $selectedBranchId = $user->isAdmin()
            ? ($request->session()->get('selected_branch_id') ?: null)
            : ($user->branch_id ? (int) $user->branch_id : null);
        $inventoryLocations = Location::query()
            ->with(['categories' => fn ($query) => $query->orderBy('name')])
            ->when($selectedBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('inventory.index', [
            'branches' => Branch::orderBy('name')->get(),
            'exportBranches' => $user->isAdmin()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : Branch::query()->whereKey($user->branch_id)->get(),
            'selectedBranchId' => $selectedBranchId,
            'inventoryLocations' => $inventoryLocations,
            'inventorySubcategories' => $inventoryLocations->mapWithKeys(fn (Location $location): array => [
                $location->id => $location->categories->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values(),
            ]),
        ]);
    }

    public function data(Request $request)
    {
        $branchId = $this->resolveBranchId($request);
        $query = Item::with(['category.location', 'branch'])
            ->select('items.*')
            ->selectSub(
                InventoryTransaction::query()
                    ->selectRaw('MAX(COALESCE(transaction_date, created_at))')
                    ->whereColumn('item_id', 'items.id'),
                'last_transaction_at'
            );

        if ($branchId) {
            $query->where('items.branch_id', $branchId);
        }

        if ($locationId = $request->integer('location_id')) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('location_id', $locationId));
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('items.category_id', $categoryId);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('items.name', 'like', "%{$search}%")
                    ->orWhere('items.unit', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category.location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['name', 'category', 'location', 'branch_name', 'quantity', 'unit', 'unit_price', 'low_stock_threshold', 'created_at', 'last_transaction_at'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'name';
        }

        // Handle sorting
        if ($sort === 'branch_name') {
            $query->join('branches', 'items.branch_id', '=', 'branches.id')
                ->orderBy('branches.name', $direction)
                ->select('items.*');
        } elseif ($sort === 'category') {
            $query->join('categories', 'items.category_id', '=', 'categories.id')
                ->orderBy('categories.name', $direction)
                ->select('items.*');
        } elseif ($sort === 'location') {
            $query->join('categories', 'items.category_id', '=', 'categories.id')
                ->join('locations', 'categories.location_id', '=', 'locations.id')
                ->orderBy('locations.name', $direction)
                ->select('items.*');
        } elseif ($sort === 'last_transaction_at') {
            $query->orderBy('last_transaction_at', $direction);
        } else {
            $query->orderBy('items.'.$sort, $direction);
        }

        $rows = $query->paginate($this->perPage($request, 10));

        $rows->getCollection()->transform(function (Item $item): array {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category?->name,
                'category_label' => $item->category?->name,
                'location' => $item->category?->location?->name,
                'branch_name' => $item->branch?->name,
                'branch_id' => $item->branch_id,
                'remaining_item' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'low_stock_threshold' => $item->low_stock_threshold,
                'supplier_name' => null,
                'last_transaction_at' => $item->last_transaction_at
                    ? \Illuminate\Support\Carbon::parse($item->last_transaction_at)->format('M d, Y H:i')
                    : null,
                'allows_decimal_quantity' => InventoryQuantity::allowsDecimals($item->unit),
            ];
        });

        return response()->json($rows);
    }

    public function itemsByBranch(Request $request, Branch $branch): JsonResponse
    {
        $activeBranchId = $this->resolveBranchId($request);
        if ($activeBranchId && ! $request->user()->isAdmin() && (int) $branch->id !== (int) $activeBranchId) {
            abort(403, 'You can only view items for your active branch.');
        }

        $items = Item::query()
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'unit_price', 'quantity']);

        return response()->json($items);
    }

    public function create()
    {
        $request = request();
        $branchId = $this->resolveBranchId($request);
        $selectedCategoryId = $request->integer('category_id') ?: null;

        $categoryOptions = Category::with('location')
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'branch_id' => $category->branch_id,
                'label' => ($category->location?->name ? $category->location->name.' > ' : '').$category->name,
            ]);

        $selectedCategoryOption = $selectedCategoryId
            ? $categoryOptions->firstWhere('id', $selectedCategoryId)
            : null;

        return view('inventory.create', [
            'branches' => Branch::orderBy('name')->get(),
            'categoryOptions' => $categoryOptions,
            'selectedCategoryId' => $selectedCategoryOption['id'] ?? null,
            'selectedCategoryBranchId' => $selectedCategoryOption['branch_id'] ?? null,
            'unitOptions' => InventoryUnit::options(),
        ]);
    }

    public function store(StoreItemRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $branchId = $this->resolveBranchId($request);

        if (! $user->isAdmin()) {
            $data['branch_id'] = $user->branch_id;
        } elseif ($branchId && (int) ($data['branch_id'] ?? 0) !== $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please use the selected branch for this action.',
            ]);
        }

        $category = Category::findOrFail($data['category_id']);
        if ((int) $category->branch_id !== (int) $data['branch_id']) {
            throw ValidationException::withMessages([
                'category_id' => 'Selected category does not belong to the selected branch.',
            ]);
        }

        $item = Item::create([
            ...$data,
            'quantity' => $data['quantity'] ?? 0,
            'created_by' => $user->id,
        ]);

        return redirect()->route('inventory.index')->with('success', 'Inventory item created successfully.');
    }

    public function edit(Item $inventory)
    {
        $this->ensureItemInActiveBranch(request(), $inventory);
        $branchId = $this->resolveBranchId(request());

        return view('inventory.edit', [
            'inventory' => $inventory,
            'unitOptions' => InventoryUnit::options(),
            'categoryOptions' => Category::with('location')
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'label' => ($category->location?->name ? $category->location->name.' > ' : '').$category->name,
                ]),
        ]);
    }

    public function update(Request $request, Item $inventory)
    {
        $this->ensureItemInActiveBranch($request, $inventory);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('items', 'sku')->ignore($inventory->id)],
        ]);

        $category = Category::findOrFail($validated['category_id']);
        if ((int) $category->branch_id !== (int) $inventory->branch_id) {
            throw ValidationException::withMessages([
                'category_id' => 'Selected category does not belong to this item branch.',
            ]);
        }

        $inventory->update($validated);

        return redirect()->route('inventory.index')->with('success', 'Inventory item updated successfully.');
    }

    public function destroy(Item $inventory)
    {
        $this->ensureItemInActiveBranch(request(), $inventory);
        $inventory->delete();

        return redirect()->route('inventory.index')->with('success', 'Inventory item deleted successfully.');
    }

    public function stockIn(Request $request, Item $inventory)
    {
        $this->ensureItemInActiveBranch($request, $inventory);

        $validated = $request->validate([
            'quantity' => InventoryQuantity::validationRules($inventory->unit),
            'reason' => ['nullable', 'string', 'max:255'],
            'custom_reason' => ['nullable', 'required_if:reason,Others', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:now'],
        ], InventoryQuantity::validationMessages($inventory->unit));

        $quantity = (float) $validated['quantity'];
        $reason = $this->manualAdjustmentReason($validated, 'Manual stock-in');

        DB::transaction(function () use ($inventory, $quantity, $validated, $reason, $request): void {
            $inventory = Item::query()->lockForUpdate()->findOrFail($inventory->id);
            $beginning = (float) $inventory->quantity;
            $this->inventoryService->increase($inventory, $quantity);
            $remaining = (float) $inventory->quantity;

            InventoryTransaction::create([
                'item_id' => $inventory->id,
                'branch_id' => $inventory->branch_id,
                'type' => 'in',
                'quantity' => $quantity,
                'beginning_quantity' => $beginning,
                'remaining_quantity' => $remaining,
                'transaction_price' => $inventory->unit_price ? (float) $inventory->unit_price : null,
                'transaction_date' => $validated['transaction_date'] ?? now(),
                'reason' => $reason,
                'status' => 'approved',
                'notes' => 'Manual stock adjustment.',
                'created_by' => $request->user()?->id,
            ]);
        });

        return redirect()->route('inventory.index')->with('success', 'Stock added and transaction logged successfully.');
    }

    public function deduct(Request $request, Item $inventory)
    {
        $this->ensureItemInActiveBranch($request, $inventory);

        $validated = $request->validate([
            'quantity' => [...InventoryQuantity::validationRules($inventory->unit), 'max:'.(float) $inventory->quantity],
            'reason' => ['required', 'string', 'max:255'],
            'custom_reason' => ['nullable', 'required_if:reason,Others', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:now'],
        ], InventoryQuantity::validationMessages($inventory->unit));

        $quantity = (float) $validated['quantity'];
        $reason = $this->manualAdjustmentReason($validated);

        DB::transaction(function () use ($inventory, $quantity, $validated, $reason, $request): void {
            $inventory = Item::query()->lockForUpdate()->findOrFail($inventory->id);

            if ((float) $inventory->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity cannot exceed the remaining stock.',
                ]);
            }

            $beginning = (float) $inventory->quantity;
            $unitPrice = $inventory->unit_price ? (float) $inventory->unit_price : null;
            $totalPrice = $unitPrice !== null ? $unitPrice * $quantity : null;

            $this->inventoryService->decrease($inventory, $quantity);
            $remaining = (float) $inventory->quantity;

            InventoryTransaction::create([
                'item_id' => $inventory->id,
                'branch_id' => $inventory->branch_id,
                'type' => 'out',
                'quantity' => $quantity,
                'beginning_quantity' => $beginning,
                'remaining_quantity' => $remaining,
                'transaction_price' => $totalPrice,
                'transaction_date' => $validated['transaction_date'] ?? now(),
                'reason' => $reason,
                'status' => 'approved',
                'notes' => 'Manual stock adjustment.',
                'created_by' => $request->user()?->id,
            ]);
        });

        return redirect()->route('inventory.index')->with('success', 'Stock deducted and transaction logged successfully.');
    }

    private function manualAdjustmentReason(array $validated, ?string $default = null): ?string
    {
        $reason = trim((string) ($validated['reason'] ?? ''));

        if ($reason === 'Others') {
            $customReason = trim((string) ($validated['custom_reason'] ?? ''));

            return $customReason !== '' ? $customReason : $default;
        }

        return $reason !== '' ? $reason : $default;
    }

    public function transactions(Request $request)
    {
        $branchId = $this->resolveBranchId($request);
        $sort = (string) $request->input('sort', 'transaction_date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['log_id', 'transaction_date', 'branch_name', 'item_id', 'item_name', 'unit', 'type', 'beginning_quantity', 'quantity', 'remaining_quantity', 'transaction_price', 'status', 'reason', 'created_by'];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'transaction_date';
        }

        $transactions = InventoryTransaction::with(['inventory', 'branch', 'creator'])
            ->select('inventory_transactions.*')
            ->when($branchId, fn ($query, $branchId) => $query->where('inventory_transactions.branch_id', $branchId))
            ->when(request('search'), fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('log_id', 'like', "%$s%")
                    ->orWhereHas('inventory', fn ($inner) => $inner->where('name', 'like', "%$s%"))
                    ->orWhere('reason', 'like', "%$s%");
            }))
            ->when($sort === 'branch_name', fn ($query) => $query
                ->leftJoin('branches as transaction_branch_sort', 'inventory_transactions.branch_id', '=', 'transaction_branch_sort.id')
                ->orderBy('transaction_branch_sort.name', $direction)
            )
            ->when(in_array($sort, ['item_name', 'unit'], true), fn ($query) => $query
                ->leftJoin('items as transaction_item_sort', 'inventory_transactions.item_id', '=', 'transaction_item_sort.id')
                ->orderBy($sort === 'item_name' ? 'transaction_item_sort.name' : 'transaction_item_sort.unit', $direction)
            )
            ->when($sort === 'created_by', fn ($query) => $query
                ->leftJoin('users as transaction_creator_sort', 'inventory_transactions.created_by', '=', 'transaction_creator_sort.id')
                ->orderBy('transaction_creator_sort.name', $direction)
            )
            ->when(in_array($sort, ['log_id', 'transaction_date', 'item_id', 'type', 'beginning_quantity', 'quantity', 'remaining_quantity', 'transaction_price', 'status', 'reason'], true), fn ($query) => $query->orderBy("inventory_transactions.{$sort}", $direction))
            ->orderBy('inventory_transactions.transaction_date', 'desc')
            ->paginate($this->perPage(request(), 10))->withQueryString();

        return view('transactions.index', compact('transactions'));
    }

    public function transactionCreate(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        return view('transactions.create', [
            'items' => Item::with(['category.location'])
                ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function transactionStore(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'type' => ['required', 'in:in,out'],
            'reason' => ['required', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $errors = [];

        // Pre-validate stock sufficiency for stock-out
        if ($validated['type'] === 'out') {
            foreach ($validated['items'] as $index => $row) {
                $item = Item::findOrFail($row['item_id']);
                $qty = (float) $row['quantity'];
                if ($qty > (float) $item->quantity) {
                    $errors["items.{$index}.quantity"] = "Insufficient stock for \"{$item->name}\". Available: {$item->quantity}, requested: {$qty}.";
                }
            }
        }

        // Validate branch ownership
        foreach ($validated['items'] as $index => $row) {
            $item = Item::findOrFail($row['item_id']);
            if ($branchId && (int) $item->branch_id !== $branchId) {
                $errors["items.{$index}.item_id"] = 'Selected item is not in your active branch.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($validated, $request): void {
            foreach ($validated['items'] as $row) {
                $item = Item::query()->lockForUpdate()->findOrFail($row['item_id']);
                $quantity = (float) $row['quantity'];
                $beginning = (float) $item->quantity;
                $transactionPrice = $item->unit_price !== null
                    ? (float) $item->unit_price * $quantity
                    : null;

                if ($validated['type'] === 'in') {
                    $this->inventoryService->increase($item, $quantity);
                } else {
                    $this->inventoryService->decrease($item, $quantity);
                }

                $remaining = (float) $item->fresh()->quantity;

                InventoryTransaction::create([
                    'item_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'type' => $validated['type'],
                    'quantity' => $quantity,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price' => $transactionPrice,
                    'transaction_date' => $validated['transaction_date'] ?? now(),
                    'reason' => $validated['reason'],
                    'status' => 'approved',
                    'created_by' => $request->user()?->id,
                ]);
            }
        });

        $count = count($validated['items']);
        $label = $count === 1 ? 'Transaction' : "{$count} transactions";

        return redirect()->route('transactions.index')->with('success', "{$label} recorded successfully.");
    }

    public function transactionsPending(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        $transactions = InventoryTransaction::with(['inventory', 'creator'])
            ->where('status', 'pending')
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(request('search'), fn ($q, $s) => $q->where(function ($query) use ($s): void {
                $query->where('log_id', 'like', "%$s%")
                    ->orWhereHas('inventory', fn ($inner) => $inner->where('name', 'like', "%$s%"))
                    ->orWhere('reason', 'like', "%$s%");
            }))
            ->latest()
            ->paginate($this->perPage(request(), 20))->withQueryString();

        return view('transactions.pending', compact('transactions'));
    }

    public function lowStock(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $locationId = $filters['location_id'] ?? null;
        $categoryId = $filters['category_id'] ?? null;

        $items = Item::with(['category.location', 'branch'])
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($locationId, fn ($query, $id) => $query->whereHas('category', fn ($inner) => $inner->where('location_id', $id)))
            ->when($categoryId, fn ($query, $id) => $query->where('category_id', $id))
            ->orderBy('quantity')
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        $locations = Location::query()
            ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($locationId, fn ($query, $id) => $query->where('location_id', $id))
            ->orderBy('name')
            ->get();

        return view('inventory.low-stock', compact('items', 'locations', 'categories', 'locationId', 'categoryId'));
    }

    public function sendLowStockEmails(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:items,id'],
        ]);

        $branchId = $this->resolveBranchId($request);

        $items = Item::query()
            ->whereIn('id', $validated['item_ids'])
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'No eligible low stock items were selected.');
        }

        foreach ($items as $item) {
            SendLowStockEmail::dispatch($item->id, force: true);
        }

        return back()->with('success', 'Low stock email queued for '.$items->count().' item(s).');
    }

    public function export(Request $request)
    {
        $branchId = $request->integer('branch_id');
        if (! $branchId) {
            return back()
                ->with('error', 'Please select a branch.')
                ->with('showExportModal', true);
        }

        $branch = Branch::query()
            ->where('is_active', true)
            ->find($branchId);

        if (! $branch) {
            return back()
                ->with('error', 'Please select a valid branch.')
                ->with('showExportModal', true);
        }

        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== (int) $branch->id) {
            abort(403, 'You can only export inventory for your assigned branch.');
        }

        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $branch->name));

        $filename = 'inventory-'.trim($slug, '-').'-'.now()->format('Ymd-His').'.xlsx';

        $items = Item::query()
            ->with(['category.location'])
            ->where('branch_id', $branch->id)
            ->select('items.*')
            ->selectSub(
                InventoryTransaction::query()
                    ->select('log_id')
                    ->whereColumn('item_id', 'items.id')
                    ->latest('created_at')
                    ->limit(1),
                'last_activity_log_id'
            )
            ->selectSub(
                InventoryTransaction::query()
                    ->selectRaw('MAX(COALESCE(transaction_date, created_at))')
                    ->whereColumn('item_id', 'items.id'),
                'last_activity_at'
            )
            ->orderBy('name')
            ->get();

        $data = $items->map(fn (Item $item): array => [
            'item_id' => $item->sku ?: (string) $item->id,
            'item_name' => $item->name,
            'category' => $item->category?->location?->name ?? '',
            'subcategory' => $item->category?->name ?? '',
            'unit' => $item->unit ?? '',
            'last_activity_id' => $item->last_activity_log_id ?? '',
            'last_activity_date' => $item->last_activity_at
                ? \Illuminate\Support\Carbon::parse($item->last_activity_at)->format('M d, Y H:i')
                : '',
            'item_price_raw' => (float) ($item->unit_price ?? 0),
            'remaining_qty_raw' => (float) $item->quantity,
            'total_price_raw' => (float) ($item->unit_price ?? 0) * (float) $item->quantity,
        ])->values()->all();

        $totalInventoryCost = array_sum(array_column($data, 'total_price_raw'));
        $title = 'Inventory Snapshot — '.$branch->name.' — '.now()->format('M d, Y');

        return Excel::download(new InventoryExport($data, $title, $totalInventoryCost), $filename);
    }

    public function transfer(Request $request, Item $inventory): RedirectResponse
    {
        $this->ensureItemInActiveBranch($request, $inventory);

        $validated = $request->validate([
            'destination_branch_id' => ['required', 'exists:branches,id'],
            'destination_item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:'.(float) $inventory->quantity],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int) $validated['destination_branch_id'] === (int) $inventory->branch_id) {
            return back()->with('error', 'Destination branch must be different from the source branch.');
        }

        $destinationItem = Item::query()
            ->whereKey($validated['destination_item_id'])
            ->where('branch_id', $validated['destination_branch_id'])
            ->first();

        if (! $destinationItem) {
            throw ValidationException::withMessages([
                'destination_item_id' => 'The destination item must belong to the selected destination branch.',
            ]);
        }

        if (! InventoryUnit::matches($inventory->unit, $destinationItem->unit)) {
            throw ValidationException::withMessages([
                'destination_item_id' => "The destination item unit ({$destinationItem->unit}) must match the source item unit ({$inventory->unit}).",
            ]);
        }

        DB::transaction(function () use ($inventory, $validated, $request): void {
            $delivery = Delivery::create([
                'reference_number' => 'TRF-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'delivery_date' => now()->toDateString(),
                'source_branch_id' => $inventory->branch_id,
                'destination_branch_id' => $validated['destination_branch_id'],
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            DeliveryItem::create([
                'delivery_id' => $delivery->id,
                'source_item_id' => $inventory->id,
                'item_id' => $validated['destination_item_id'],
                'description' => $validated['reason'] ?? 'Branch Transfer',
                'allocated_to' => 'inventory',
                'quantity' => $validated['quantity'],
                'unit' => $inventory->unit,
            ]);

            Transfer::create([
                'from_branch_id' => $inventory->branch_id,
                'to_branch_id' => $validated['destination_branch_id'],
                'delivery_id' => $delivery->id,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('deliveries.index')->with('success', 'Transfer initiated. A pending delivery has been created for approval.');
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    private function ensureItemInActiveBranch(Request $request, Item $item): void
    {
        $branchId = $this->resolveBranchId($request);

        if ($branchId && (int) $item->branch_id !== $branchId) {
            abort(403, 'This item is outside your active branch.');
        }
    }
}
