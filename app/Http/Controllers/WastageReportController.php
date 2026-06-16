<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\WastageReport;
use App\Services\WastageInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class WastageReportController extends Controller
{
    public function __construct(private readonly WastageInventoryService $wastageInventoryService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(
            WastageReport::with(['items.item', 'items.convertedItem'])
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
                ->latest()
                ->paginate((int) request('per_page', 20))
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $id)
    {
        $validated = $request->validate([
            'production_order_id' => ['prohibited'],
            'branch_id' => ['prohibited'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.scrap_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity_lost' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.convert_to_item_id' => ['nullable', 'exists:items,id', 'required_with:items.*.converted_quantity'],
            'items.*.converted_quantity' => ['nullable', 'numeric', 'gt:0', 'required_with:items.*.convert_to_item_id'],
        ]);

        $production = ProductionOrder::query()->findOrFail($id);

        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== (int) $production->branch_id) {
            abort(403, 'This production order is outside your branch.');
        }

        $report = DB::transaction(function () use ($validated, $request, $production): WastageReport {
            return $this->wastageInventoryService->createFromProduction($production, $validated['items'], $request->user()->id);
        });

        return response()->json($report->load('items'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $report = WastageReport::with(['items.item', 'items.convertedItem'])->findOrFail($id);
        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== (int) $report->branch_id) {
            abort(403, 'This wastage report is outside your branch.');
        }

        return response()->json($report);
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
        $report = WastageReport::findOrFail($id);
        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== (int) $report->branch_id) {
            abort(403, 'This wastage report is outside your branch.');
        }
        $report->delete();

        return response()->json(status: 204);
    }
}

