<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuCategoryController extends Controller
{
    private function branchScope()
    {
        $user = auth()->user();
        $query = MenuCategory::with(['branch', 'menus']);

        if (!$user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif (session('selected_branch_id')) {
            $query->where('branch_id', session('selected_branch_id'));
        }

        return $query;
    }

    private function authorizeBranch(int $branchId): void
    {
        $user = auth()->user();
        if (!$user->isAdmin() && (int) $user->branch_id !== $branchId) {
            abort(403);
        }
    }

    public function index(): View
    {
        return view('menu-categories.index');
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['id', 'name', 'branch_id', 'created_at'];

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'name';
        }

        $categories = $this->branchScope()
            ->withCount('menus')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return response()->json($categories);
    }

    public function create(): View
    {
        return view('menu-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string',
        ]);

        if (!$user->isAdmin() && (int) $data['branch_id'] !== (int) $user->branch_id) {
            abort(403);
        }

        $exists = MenuCategory::where('branch_id', $data['branch_id'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A category with this name already exists in the selected branch.'])->withInput();
        }

        MenuCategory::create($data);

        return redirect()->route('menu-categories.index')->with('success', 'Menu category created successfully.');
    }

    public function edit(MenuCategory $menuCategory): View
    {
        $this->authorizeBranch($menuCategory->branch_id);
        return view('menu-categories.edit', compact('menuCategory'));
    }

    public function update(Request $request, MenuCategory $menuCategory): RedirectResponse
    {
        $this->authorizeBranch($menuCategory->branch_id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $exists = MenuCategory::where('branch_id', $menuCategory->branch_id)
            ->where('name', $data['name'])
            ->where('id', '!=', $menuCategory->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A category with this name already exists in this branch.'])->withInput();
        }

        $menuCategory->update($data);

        return redirect()->route('menu-categories.index')->with('success', 'Menu category updated successfully.');
    }

    public function destroy(MenuCategory $menuCategory): RedirectResponse
    {
        $this->authorizeBranch($menuCategory->branch_id);
        $menuCategory->delete();

        return redirect()->route('menu-categories.index')->with('success', 'Menu category deleted successfully.');
    }
}