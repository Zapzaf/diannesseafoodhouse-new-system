<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    private function branchScope()
    {
        $user = auth()->user();
        $query = Menu::with(['branch', 'itemCategory.location']);

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
        $menus = $this->branchScope()
            ->withCount('items')
            ->orderBy('category')
            ->orderBy('name')
            ->paginate((int) request('per_page', 25))->withQueryString();

        return view('menu.index', compact('menus'));
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['id', 'name', 'category', 'selling_price', 'branch_id', 'created_at'];

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'name';
        }

        $menus = $this->branchScope()
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return response()->json($menus);
    }

    public function create(): View
    {
        $user = auth()->user();
        $branches = Branch::where('is_active', true)->get();

        $branchId = $user->isAdmin()
            ? (session('selected_branch_id') ?? ($branches->first()->id ?? null))
            : $user->branch_id;

        $items = Item::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with('category.location')
            ->orderBy('name')
            ->get()
            ->filter(fn($item) => (float) $item->quantity > 0);

        $menuCategories = MenuCategory::with('branch')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('menu.create', compact('branches', 'items', 'menuCategories', 'branchId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'branch_id'         => 'required|exists:branches,id',
            'name'              => 'required|string|max:255',
            'menu_description'  => 'nullable|string|max:2000',
            'selling_price'     => 'required|numeric|min:0',
            'menu_category_id'  => 'required|exists:menu_categories,id',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ingredients'       => 'required|array|min:1',
            'ingredients.*.item_id'            => 'required|exists:items,id',
            'ingredients.*.quantity_required'  => 'required|numeric|min:0.01',
        ]);

        if (!$user->isAdmin() && (int) $data['branch_id'] !== $user->branch_id) {
            abort(403);
        }

        $menuCategory = MenuCategory::findOrFail($data['menu_category_id']);
        if ((int) $menuCategory->branch_id !== (int) $data['branch_id']) {
            throw ValidationException::withMessages([
                'menu_category_id' => 'Selected category must belong to the same branch.',
            ]);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menus', 'public');
        }

        $menu = Menu::create([
            'branch_id'        => $data['branch_id'],
            'menu_category_id' => $menuCategory->id,
            'name'             => $data['name'],
            'menu_description' => $data['menu_description'] ?? null,
            'selling_price'    => $data['selling_price'],
            'image'            => $imagePath,
            'category'         => $menuCategory->name,
            'created_by'       => $user->id,
        ]);

        $this->syncIngredients($menu, $data['ingredients']);

        return redirect()->route('menus.index')->with('success', 'Menu item created successfully.');
    }

    public function show(Menu $menu): View
    {
        $this->authorizeBranch($menu->branch_id);
        $menu->load(['branch', 'itemCategory.location', 'items.category.location']);

        return view('menu.show', compact('menu'));
    }

    public function edit(Menu $menu): View
    {
        $this->authorizeBranch($menu->branch_id);
        $menu->load('items');

        $branches = Branch::where('is_active', true)->get();
        $branchId = $menu->branch_id;

        $items = Item::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with('category.location')
            ->orderBy('name')
            ->get();

        $menuCategories = MenuCategory::with('branch')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('menu.edit', compact('menu', 'branches', 'items', 'menuCategories', 'branchId'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorizeBranch($menu->branch_id);

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'menu_description'  => 'nullable|string|max:2000',
            'selling_price'     => 'required|numeric|min:0',
            'menu_category_id'  => 'required|exists:menu_categories,id',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ingredients'       => 'required|array|min:1',
            'ingredients.*.item_id'            => 'required|exists:items,id',
            'ingredients.*.quantity_required'  => 'required|numeric|min:0.01',
        ]);

        $menuCategory = MenuCategory::findOrFail($data['menu_category_id']);
        if ((int) $menuCategory->branch_id !== (int) $menu->branch_id) {
            throw ValidationException::withMessages([
                'menu_category_id' => 'Selected category must belong to the same branch.',
            ]);
        }

        $updateData = [
            'menu_category_id' => $menuCategory->id,
            'name'             => $data['name'],
            'menu_description' => $data['menu_description'] ?? null,
            'selling_price'    => $data['selling_price'],
            'category'         => $menuCategory->name,
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($menu->image && Storage::disk('public')->exists($menu->image)) {
                Storage::disk('public')->delete($menu->image);
            }
            $updateData['image'] = $request->file('image')->store('menus', 'public');
        }

        $menu->update($updateData);

        $this->syncIngredients($menu, $data['ingredients']);

        return redirect()->route('menus.index')->with('success', 'Menu item updated successfully.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $this->authorizeBranch($menu->branch_id);
        $menu->items()->detach();
        $menu->delete();

        return redirect()->route('menus.index')->with('success', 'Menu item deleted.');
    }

    private function syncIngredients(Menu $menu, array $ingredients): void
    {
        $syncData = [];
        foreach ($ingredients as $ing) {
            if (!empty($ing['item_id'])) {
                $item = Item::find($ing['item_id']);
                if (!$item || (int) $item->branch_id !== (int) $menu->branch_id) {
                    throw ValidationException::withMessages([
                        'ingredients' => 'All ingredients must belong to the same branch as the menu item.',
                    ]);
                }

                $syncData[(int) $ing['item_id']] = [
                    'quantity_required' => (float) $ing['quantity_required'],
                ];
            }
        }
        $menu->items()->sync($syncData);
    }
}
