<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Supplier::class);

        return response()->json(Supplier::latest()->paginate($this->perPage(request(), 20))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($supplier, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('view', $supplier);

        return response()->json($supplier);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('update', $supplier);

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['company', 'sole_proprietorship'])],
            'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'business_name' => ['required_if:type,sole_proprietorship', 'nullable', 'string', 'max:255'],
            'owner_name' => ['required_if:type,sole_proprietorship', 'nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier->update($validated);

        return response()->json($supplier->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('delete', $supplier);
        $supplier->delete();

        return response()->json(status: 204);
    }
}

