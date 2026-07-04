<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Location::with('categories')->latest()->paginate($this->perPage(request(), 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Location::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        return response()->json(Location::create($validated), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $location = Location::with('categories')->findOrFail($id);
        $this->authorize('view', $location);

        return response()->json($location);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $location = Location::findOrFail($id);
        $this->authorize('update', $location);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $location->update($validated);

        return response()->json($location->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $location = Location::findOrFail($id);
        $this->authorize('delete', $location);
        $location->delete();

        return response()->json(status: 204);
    }
}

