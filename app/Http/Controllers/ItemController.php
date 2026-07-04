<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Item::with(['category.location', 'branch']);
        if ($request->user()->isAdmin() && $request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        } elseif (! $request->user()->isAdmin()) {
            $query->where('branch_id', $request->user()->branch_id);
        }

        return response()->json($query->latest()->paginate($this->perPage(request(), 10))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreItemRequest $request)
    {
        $this->authorize('create', Item::class);

        $payload = $request->validated();
        $user = $request->user();

        if (! $user->isAdmin()) {
            $payload['branch_id'] = $user->branch_id;
        }

        if (empty($payload['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please select a branch for this item.',
            ]);
        }

        $this->ensureCategoryBelongsToBranch((int) $payload['category_id'], (int) $payload['branch_id']);

        // Since opening balance is removed, initialize quantity to 0.
        $item = Item::create([
            ...$payload,
            'quantity' => 0,
            'created_by' => $user->id,
        ]);

        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Item::with(['category.location', 'branch'])->findOrFail($id);
        $this->authorize('view', $item);

        return response()->json($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $item = Item::findOrFail($id);
        $this->authorize('update', $item);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('items', 'sku')->ignore($item->id)],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['category_id'])) {
            $this->ensureCategoryBelongsToBranch((int) $validated['category_id'], (int) $item->branch_id);
        }
        $item->update($validated);

        return response()->json($item->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Item::findOrFail($id);
        $this->authorize('delete', $item);
        $item->delete();

        return response()->json(status: 204);
    }

    private function ensureCategoryBelongsToBranch(int $categoryId, int $branchId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        if ((int) $category->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category must belong to the item branch.',
            ]);
        }
    }
}
