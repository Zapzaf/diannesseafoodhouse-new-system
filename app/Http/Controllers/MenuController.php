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
use Illuminate\Support\Facades\DB;
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
            ->paginate($this->perPage(request(), 25))->withQueryString();

        $menuCategories = MenuCategory::query()
            ->when(!auth()->user()->isAdmin(), fn($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->when(auth()->user()->isAdmin() && session('selected_branch_id'), fn($q) => $q->where('branch_id', session('selected_branch_id')))
            ->orderBy('name')
            ->get();

        return view('menu.index', compact('menus', 'menuCategories'));
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $menuCategoryId = (int) $request->input('menu_category_id', 0);
        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['id', 'name', 'menu_description', 'category', 'selling_price', 'items_count', 'branch_id', 'created_at'];

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
            ->when($menuCategoryId > 0, fn($query) => $query->where('menu_category_id', $menuCategoryId))
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return response()->json($menus);
    }

    public function create(): View
    {
        $user = auth()->user();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        $branchId = $user->isAdmin()
            ? (session('selected_branch_id') ?? ($branches->first()->id ?? null))
            : $user->branch_id;

        $loadAllBranches = $user->isAdmin() && !session('selected_branch_id');

        $items = Item::query()
            ->when(!$loadAllBranches && $branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with('category.location')
            ->orderBy('name')
            ->get();

        $menuCategories = MenuCategory::query()
            ->with('branch')
            ->when(!$loadAllBranches && $branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        // Branches with "Disable Ingredients" turned on (Settings > Branch Settings)
        // should pre-check "No Ingredients" by default for new menu items.
        $branchIngredientDefaults = $branches->pluck('disable_ingredients', 'id');

        return view('menu.create', compact('branches', 'items', 'menuCategories', 'branchId', 'branchIngredientDefaults'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $noIngredients = $request->boolean('no_ingredients');

        $data = $request->validate([
            'branch_id'         => 'required|exists:branches,id',
            'name'              => 'required|string|max:255',
            'menu_description'  => 'nullable|string|max:2000',
            'no_ingredients'    => 'nullable|boolean',
            'selling_price'     => 'required|numeric|min:0',
            'menu_category_id'  => 'required|exists:menu_categories,id',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ingredients'       => $noIngredients ? 'nullable|array' : 'required|array|min:1',
            'ingredients.*.item_id'            => $noIngredients ? 'nullable' : ['required', 'exists:items,id', 'distinct'],
            'ingredients.*.quantity_required'  => $noIngredients ? 'nullable' : 'required|numeric|min:0.01',
        ], [
            'ingredients.required' => 'Add at least one ingredient to save this menu item.',
            'ingredients.min' => 'Add at least one ingredient to save this menu item.',
            'ingredients.*.item_id.required' => 'Choose an inventory item for each ingredient row.',
            'ingredients.*.item_id.distinct' => 'Each ingredient can only be added once.',
            'ingredients.*.quantity_required.required' => 'Enter the required quantity for every ingredient.',
            'ingredients.*.quantity_required.min' => 'Ingredient quantity must be greater than zero.',
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

        $ingredients = $noIngredients ? [] : $data['ingredients'];

        $ingredientItems = Item::query()
            ->whereIn('id', collect($ingredients)->pluck('item_id')->all())
            ->get()
            ->keyBy('id');

        $primaryCategoryId = $noIngredients
            ? null
            : $this->resolveMenuCategoryIdFromIngredients($ingredients, $ingredientItems, (int) $data['branch_id']);

        $imagePath = null;
        try {
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('menus', 'public');
            }

            DB::transaction(function () use ($data, $menuCategory, $primaryCategoryId, $imagePath, $user, $ingredientItems, $ingredients, $noIngredients): void {
                $menu = Menu::create([
                    'branch_id'        => $data['branch_id'],
                    'category_id'      => $primaryCategoryId,
                    'menu_category_id' => $menuCategory->id,
                    'name'             => $data['name'],
                    'menu_description' => $data['menu_description'] ?? null,
                    'no_ingredients'   => $noIngredients,
                    'selling_price'    => $data['selling_price'],
                    'image'            => $imagePath,
                    'category'         => $menuCategory->name,
                    'created_by'       => $user->id,
                ]);

                $this->syncIngredients($menu, $ingredients, $ingredientItems);
            });
        } catch (\Throwable $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $e;
        }

        return redirect()->route('menus.index')->with('success', 'Menu item created successfully.');
    }

    public function show(Menu $menu): View
    {
        $this->authorizeBranch($menu->branch_id);
        $menu->load(['branch', 'itemCategory.location', 'items.category.location']);

        return view('menu.show', compact('menu'));
    }

    public function edit(Request $request, Menu $menu): View
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

        // Carry the list's current page/search/sort state through the edit form so
        // saving or cancelling returns to where the admin was, not back to page 1.
        $returnUrl = $this->safeMenusReturnUrl($request->header('referer'));

        return view('menu.edit', compact('menu', 'branches', 'items', 'menuCategories', 'branchId', 'returnUrl'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorizeBranch($menu->branch_id);

        $noIngredients = $request->boolean('no_ingredients');

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'menu_description'  => 'nullable|string|max:2000',
            'no_ingredients'    => 'nullable|boolean',
            'selling_price'     => 'required|numeric|min:0',
            'menu_category_id'  => 'required|exists:menu_categories,id',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'return_to'         => 'nullable|string',
            'ingredients'       => $noIngredients ? 'nullable|array' : 'required|array|min:1',
            'ingredients.*.item_id'            => $noIngredients ? 'nullable' : ['required', 'exists:items,id', 'distinct'],
            'ingredients.*.quantity_required'  => $noIngredients ? 'nullable' : 'required|numeric|min:0.01',
        ], [
            'ingredients.required' => 'Add at least one ingredient to save this menu item.',
            'ingredients.min' => 'Add at least one ingredient to save this menu item.',
            'ingredients.*.item_id.required' => 'Choose an inventory item for each ingredient row.',
            'ingredients.*.item_id.distinct' => 'Each ingredient can only be added once.',
            'ingredients.*.quantity_required.required' => 'Enter the required quantity for every ingredient.',
            'ingredients.*.quantity_required.min' => 'Ingredient quantity must be greater than zero.',
        ]);

        $menuCategory = MenuCategory::findOrFail($data['menu_category_id']);
        if ((int) $menuCategory->branch_id !== (int) $menu->branch_id) {
            throw ValidationException::withMessages([
                'menu_category_id' => 'Selected category must belong to the same branch.',
            ]);
        }

        $ingredients = $noIngredients ? [] : $data['ingredients'];

        $ingredientItems = Item::query()
            ->whereIn('id', collect($ingredients)->pluck('item_id')->all())
            ->get()
            ->keyBy('id');

        $primaryCategoryId = $noIngredients
            ? null
            : $this->resolveMenuCategoryIdFromIngredients($ingredients, $ingredientItems, (int) $menu->branch_id);

        $updateData = [
            'category_id'      => $primaryCategoryId,
            'menu_category_id' => $menuCategory->id,
            'name'             => $data['name'],
            'menu_description' => $data['menu_description'] ?? null,
            'no_ingredients'   => $noIngredients,
            'selling_price'    => $data['selling_price'],
            'category'         => $menuCategory->name,
        ];

        $newImagePath = null;
        $oldImagePath = $menu->image;
        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('menus', 'public');
            $updateData['image'] = $newImagePath;
        }

        try {
            DB::transaction(function () use ($menu, $updateData, $ingredients, $ingredientItems): void {
                $menu->update($updateData);
                $this->syncIngredients($menu, $ingredients, $ingredientItems);
            });
        } catch (\Throwable $e) {
            if ($newImagePath && Storage::disk('public')->exists($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $e;
        }

        if ($newImagePath && $oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        $returnUrl = $this->safeMenusReturnUrl($data['return_to'] ?? null) ?? route('menus.index');

        return redirect()->to($returnUrl)->with('success', 'Menu item updated successfully.');
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorizeBranch($menu->branch_id);
        $menu->items()->detach();
        $menu->delete();

        // The delete button lives on the index list itself, so the browser's
        // Referer header already carries the page/search/sort the admin was on.
        $returnUrl = $this->safeMenusReturnUrl($request->header('referer')) ?? route('menus.index');

        return redirect()->to($returnUrl)->with('success', 'Menu item deleted.');
    }

    /**
     * Only accept same-origin URLs that point back into the Menu Management
     * list, so a tampered return_to/referer value can't be used to redirect
     * an admin off-site (open redirect).
     */
    private function safeMenusReturnUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== request()->getHost()) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (! str_starts_with($path, parse_url(route('menus.index'), PHP_URL_PATH))) {
            return null;
        }

        return $url;
    }

    private function syncIngredients(Menu $menu, array $ingredients, $ingredientItems = null): void
    {
        $ingredientItems = $ingredientItems ?: Item::query()
            ->whereIn('id', collect($ingredients)->pluck('item_id')->all())
            ->get()
            ->keyBy('id');

        $syncData = [];
        foreach ($ingredients as $ing) {
            if (!empty($ing['item_id'])) {
                $item = $ingredientItems->get((int) $ing['item_id']);
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

    private function resolveMenuCategoryIdFromIngredients(array $ingredients, $ingredientItems, int $branchId): ?int
    {
        $categoryIds = [];

        foreach ($ingredients as $ingredient) {
            $item = $ingredientItems->get((int) ($ingredient['item_id'] ?? 0));

            if (! $item || (int) $item->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'ingredients' => 'All ingredients must belong to the same branch as the menu item.',
                ]);
            }

            if (! $item->category_id) {
                throw ValidationException::withMessages([
                    'ingredients' => 'Each ingredient must have a valid inventory category before it can be used in a menu recipe.',
                ]);
            }

            $categoryIds[] = (int) $item->category_id;
        }

        return $categoryIds[0] ?? null;
    }
}
