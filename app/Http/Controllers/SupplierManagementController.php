<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierManagementController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::when(request('search'), fn($q, $s) => $q->where('name', 'like', "%$s%"))->latest()->paginate($this->perPage(request(), 20))->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        Supplier::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }
}


