<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierManagementController extends Controller
{
    public function index(Request $request)
    {
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['name', 'type', 'contact_person', 'phone', 'email', 'created_at'];

        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $suppliers = Supplier::query()
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('name', 'like', "%{$s}%")
                        ->orWhere('company_name', 'like', "%{$s}%")
                        ->orWhere('business_name', 'like', "%{$s}%")
                        ->orWhere('owner_name', 'like', "%{$s}%")
                        ->orWhere('contact_person', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('address', 'like', "%{$s}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateSupplier($request);

        Supplier::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function edit(Request $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        $validated = $this->validateSupplier($request);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['company', 'sole_proprietorship'])],
            'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'business_name' => ['required_if:type,sole_proprietorship', 'nullable', 'string', 'max:255'],
            'owner_name' => ['required_if:type,sole_proprietorship', 'nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}


