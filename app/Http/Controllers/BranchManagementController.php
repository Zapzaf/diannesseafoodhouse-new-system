<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;

class BranchManagementController extends Controller
{
    public function index()
    {
        $branches = Branch::with('manager')->when(request('search'), fn($q, $s) => $q->where('name', 'like', "%$s%"))->orderBy('name')->paginate((int) request('per_page', 15))->withQueryString();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        $managers = User::where('role', 'branch_manager')->orderBy('name')->get();

        return view('branches.create', compact('managers'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Branch::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    }

    public function edit(Branch $branch)
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $managers = User::where('role', 'branch_manager')->orderBy('name')->get();

        return view('branches.edit', compact('branch', 'managers'));
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'address'    => ['required', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $branch->update([
            'name'       => $validated['name'],
            'address'    => $validated['address'],
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active'  => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function show(Branch $branch)
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $branch->loadMissing(['manager', 'users', 'locations.categories']);

        return view('branches.view', compact('branch'));
    }

    public function destroy(Request $request, Branch $branch)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
    }
}


