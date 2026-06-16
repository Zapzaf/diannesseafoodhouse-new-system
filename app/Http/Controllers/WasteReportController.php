<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\WasteReport;
use App\Models\WasteReportItem;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WasteReportController extends Controller
{
    private const REASONS = [
        'Spoilage',
        'Bad odor',
        'Expired',
        'Damaged',
        'Contaminated',
        'Other',
    ];

    public function __construct(private readonly InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        $reports = WasteReport::query()
            ->with(['branch', 'creator', 'items.item'])
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('id', 'like', "%{$search}%")
                        ->orWhereHas('items.item', fn ($itemQuery) => $itemQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('reason', 'like', "%{$search}%"));
                });
            })
            ->latest('report_date')
            ->latest()
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        return view('waste-reports.index', [
            'reports' => $reports,
        ]);
    }

    public function create(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        $items = Item::query()
            ->with(['category.location', 'branch'])
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
            ->orderBy('name')
            ->get();

        return view('waste-reports.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $branchId,
            'items' => $items,
            'itemOptions' => $items->map(fn (Item $item): array => [
                'id' => $item->id,
                'branch_id' => $item->branch_id,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'label' => '#'.$item->id.' - '.$item->name
                    .' ('.($item->category?->location?->name ?? 'N/A')
                    .' / '.($item->category?->name ?? 'N/A').')'
                    .' - '.number_format((float) $item->quantity, 2).' '.$item->unit,
            ])->values()->all(),
            'reasons' => self::REASONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['required', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->validateBranchAccess($request, (int) $validated['branch_id']);
        $this->validateWasteItems($validated);

        $report = DB::transaction(function () use ($validated, $request): WasteReport {
            $report = WasteReport::create([
                'branch_id' => $validated['branch_id'],
                'report_date' => $validated['report_date'],
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $row) {
                $item = Item::query()
                    ->whereKey((int) $row['item_id'])
                    ->where('branch_id', $validated['branch_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $quantity = (float) $row['quantity'];
                $beginning = (float) $item->quantity;

                if ($beginning < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$item->name}. Available: {$beginning}, requested: {$quantity}.",
                    ]);
                }

                WasteReportItem::create([
                    'waste_report_id' => $report->id,
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'reason' => $row['reason'],
                    'notes' => $row['notes'] ?? null,
                ]);

                $transactionPrice = $item->unit_price !== null
                    ? (float) $item->unit_price * $quantity
                    : null;

                $this->inventoryService->decrease($item, $quantity);
                $remaining = (float) $item->quantity;

                InventoryTransaction::create([
                    'item_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'beginning_quantity' => $beginning,
                    'remaining_quantity' => $remaining,
                    'transaction_price' => $transactionPrice,
                    'transaction_date' => $validated['report_date'],
                    'reason' => 'WASTE REPORT #'.$report->id.': '.$row['reason'],
                    'notes' => $row['notes'] ?? null,
                    'status' => 'approved',
                    'created_by' => $request->user()->id,
                ]);
            }

            return $report;
        });

        return redirect()->route('waste-reports.show', $report)->with('success', 'Waste report recorded successfully.');
    }

    public function show(Request $request, WasteReport $wasteReport): View
    {
        $this->validateBranchAccess($request, (int) $wasteReport->branch_id);

        $wasteReport->load(['branch', 'creator', 'items.item.category.location']);

        return view('waste-reports.show', [
            'wasteReport' => $wasteReport,
        ]);
    }

    private function validateWasteItems(array $validated): void
    {
        $errors = [];
        $branchId = (int) $validated['branch_id'];
        $itemsById = Item::query()
            ->whereIn('id', collect($validated['items'])->pluck('item_id')->map(fn ($id) => (int) $id)->unique())
            ->get()
            ->keyBy('id');

        $requestedByItem = [];

        foreach ($validated['items'] as $index => $row) {
            $itemId = (int) $row['item_id'];
            $item = $itemsById->get($itemId);

            if (! $item || (int) $item->branch_id !== $branchId) {
                $errors["items.{$index}.item_id"] = 'Selected waste item must belong to the report branch.';
                continue;
            }

            $requestedByItem[$itemId] = ($requestedByItem[$itemId] ?? 0) + (float) $row['quantity'];
        }

        foreach ($requestedByItem as $itemId => $requestedQuantity) {
            $item = $itemsById->get($itemId);
            if ($item && $requestedQuantity > (float) $item->quantity) {
                $firstIndex = collect($validated['items'])->search(fn ($row) => (int) $row['item_id'] === (int) $itemId);
                $errors["items.{$firstIndex}.quantity"] = "Insufficient stock for {$item->name}. Available: {$item->quantity}, requested: {$requestedQuantity}.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateBranchAccess(Request $request, int $branchId): void
    {
        $activeBranchId = $this->resolveBranchId($request);

        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== $branchId) {
            abort(403, 'This waste report is outside your branch.');
        }

        if ($activeBranchId && $activeBranchId !== $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please use the active branch for this waste report.',
            ]);
        }
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }
}
