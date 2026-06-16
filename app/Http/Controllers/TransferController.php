<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Item;
use App\Models\Transfer;
use App\Support\InventoryUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(
            Transfer::with(['delivery.items.item', 'fromBranch', 'toBranch'])
                ->when(! $request->user()->isAdmin(), function ($query) use ($request): void {
                    $query->where(function ($inner) use ($request): void {
                        $inner->where('from_branch_id', $request->user()->branch_id)
                            ->orWhere('to_branch_id', $request->user()->branch_id);
                    });
                })
                ->latest()
                ->paginate((int) request('per_page', 10))
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'exists:branches,id'],
            'to_branch_id' => ['required', 'exists:branches,id', 'different:from_branch_id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.destination_item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'string', 'max:32'],
        ]);

        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== (int) $validated['from_branch_id']) {
            abort(403, 'You can only transfer stock from your own branch.');
        }

        $this->validateTransferItems($validated);

        $transfer = DB::transaction(function () use ($validated, $request): Transfer {
            $delivery = Delivery::create([
                'reference_number' => 'TRF-DLV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'source_branch_id' => $validated['from_branch_id'],
                'destination_branch_id' => $validated['to_branch_id'],
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $sourceItem = Item::query()->findOrFail((int) $item['item_id']);
                $destinationItem = Item::query()->findOrFail((int) $item['destination_item_id']);

                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'source_item_id' => $sourceItem->id,
                    'item_id' => $destinationItem->id,
                    'description' => 'Branch Transfer',
                    'quantity' => $item['quantity'],
                    'unit' => $sourceItem->unit,
                    'allocated_to' => 'inventory',
                ]);
            }

            return Transfer::create([
                'from_branch_id' => $validated['from_branch_id'],
                'to_branch_id' => $validated['to_branch_id'],
                'delivery_id' => $delivery->id,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($transfer->load('delivery.items.item'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $transfer = Transfer::with(['delivery.items.item', 'fromBranch', 'toBranch'])->findOrFail($id);
        $this->ensureTransferAccess($request, $transfer);

        return response()->json($transfer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $transfer = Transfer::findOrFail($id);
        $this->ensureTransferAccess($request, $transfer);
        $transfer->delete();

        return response()->json(status: 204);
    }

    private function validateTransferItems(array $validated): void
    {
        $errors = [];
        $sourceItems = Item::query()
            ->whereIn('id', collect($validated['items'])->pluck('item_id')->map(fn ($id) => (int) $id)->unique())
            ->get()
            ->keyBy('id');
        $destinationItems = Item::query()
            ->whereIn('id', collect($validated['items'])->pluck('destination_item_id')->map(fn ($id) => (int) $id)->unique())
            ->get()
            ->keyBy('id');
        $requestedBySource = [];

        foreach ($validated['items'] as $index => $row) {
            $source = $sourceItems->get((int) $row['item_id']);
            $destination = $destinationItems->get((int) $row['destination_item_id']);

            if (! $source || (int) $source->branch_id !== (int) $validated['from_branch_id']) {
                $errors["items.{$index}.item_id"] = 'The source item must belong to the source branch.';
                continue;
            }

            if (! $destination || (int) $destination->branch_id !== (int) $validated['to_branch_id']) {
                $errors["items.{$index}.destination_item_id"] = 'The destination item must belong to the destination branch.';
                continue;
            }

            if (! InventoryUnit::matches($source->unit, $destination->unit)) {
                $errors["items.{$index}.destination_item_id"] = "The destination item unit ({$destination->unit}) must match the source item unit ({$source->unit}).";
            }

            $requestedBySource[$source->id] = ($requestedBySource[$source->id] ?? 0) + (float) $row['quantity'];
        }

        foreach ($requestedBySource as $sourceItemId => $requestedQuantity) {
            $source = $sourceItems->get($sourceItemId);
            if ($source && $requestedQuantity > (float) $source->quantity) {
                $firstIndex = collect($validated['items'])->search(fn ($row) => (int) $row['item_id'] === (int) $sourceItemId);
                $errors["items.{$firstIndex}.quantity"] = "Insufficient stock for {$source->name}. Available: {$source->quantity}, requested: {$requestedQuantity}.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureTransferAccess(Request $request, Transfer $transfer): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        if ((int) $request->user()->branch_id !== (int) $transfer->from_branch_id
            && (int) $request->user()->branch_id !== (int) $transfer->to_branch_id) {
            abort(403, 'This transfer is outside your branch.');
        }
    }
}
