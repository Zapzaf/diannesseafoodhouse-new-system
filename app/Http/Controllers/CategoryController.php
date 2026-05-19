<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Category::with(['location', 'branch'])->latest()->paginate((int) request('per_page', 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'exists:locations,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $location = Location::findOrFail($validated['location_id']);

        $category = Category::create([
            'name' => $validated['name'],
            'location_id' => $location->id,
            'branch_id' => $validated['branch_id'] ?? $location->branch_id,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::with(['location', 'items'])->findOrFail($id);
        $this->authorize('view', $category);

        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);
        $category->update($validated);

        return response()->json($category->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $this->authorize('delete', $category);
        $category->delete();

        return response()->json(status: 204);
    }
}

