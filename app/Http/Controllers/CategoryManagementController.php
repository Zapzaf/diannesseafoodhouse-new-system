<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryManagementController extends Controller
{
    public function module(Request $request)
    {
        return $this->allLocationCategories($request);
    }

    public function allLocationCategories(Request $request)
    {
        $branchId = $this->resolveBranchId($request);
        $user = $request->user();

        $locations = Location::query()
            ->with(['branch', 'categories' => function ($query) {
                $query->orderBy('name');
            }])
            ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        return view('categories.index', [
            'locations' => $locations,
            'selectedBranchId' => $branchId,
            'user' => $user,
        ]);
    }

    public function createLocation(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        return view('categories.create-location', [
            'user' => $request->user(),
            'selectedBranchId' => $branchId,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function createCategory(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        return view('categories.create-category', [
            'user' => $request->user(),
            'selectedBranchId' => $branchId,
            'selectedLocationId' => $request->integer('location_id') ?: null,
            'locationOptions' => Location::query()
                ->with('branch')
                ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function view(Request $request, Location $location)
    {
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $location->branch_id !== $branchId) {
            abort(403);
        }

        $selectedCategoryId = $request->integer('category_id') ?: null;
        $categoryOptions = Category::query()
            ->where('location_id', $location->id)
            ->orderBy('name')
            ->get();

        $items = Item::query()
            ->with(['category.location'])
            ->where('branch_id', $location->branch_id)
            ->whereHas('category', fn ($query) => $query->where('location_id', $location->id))
            ->when($selectedCategoryId, fn ($query, $id) => $query->where('category_id', $id))
            ->orderBy('name')
            ->get();

        return view('categories.view', [
            'user' => $request->user(),
            'location' => $location->load('branch'),
            'categoryOptions' => $categoryOptions,
            'selectedCategoryId' => $selectedCategoryId,
            'items' => $items,
        ]);
    }

    // Legacy route support: /categories/{type}
    public function index(Request $request, string $type)
    {
        return $this->allLocationCategories($request);
    }

    public function store(Request $request, string $type)
    {
        return $this->storeCategory($request);
    }

    public function storeLocation(Request $request)
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isBranchManager(), 403);
        $resolvedBranchId = $this->resolveBranchId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $branchId = $request->user()?->isAdmin()
            ? (int) ($validated['branch_id'] ?? $resolvedBranchId)
            : (int) $resolvedBranchId;

        if (! $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Select a branch first before creating a location.',
            ]);
        }

        Location::create([
            'name' => $validated['name'],
            'branch_id' => $branchId,
        ]);

        return redirect()->route('categories.all')->with('success', 'Location added successfully.');
    }

    public function storeCategory(Request $request)
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isBranchManager(), 403);
        $branchId = $this->resolveBranchId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $location = Location::findOrFail($validated['location_id']);
        if ($branchId && (int) $location->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'location_id' => 'Selected location is not in your active branch.',
            ]);
        }

        Category::create([
            'name' => $validated['name'],
            'location_id' => $location->id,
            'branch_id' => $location->branch_id,
        ]);

        return redirect()->route('categories.all')->with('success', 'Category added successfully.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $location->branch_id !== $branchId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $location->update(['name' => $validated['name']]);

        return redirect()->route('categories.all')->with('success', 'Location updated successfully.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $category->branch_id !== $branchId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $location = Location::findOrFail($validated['location_id']);
        if ((int) $location->branch_id !== (int) $category->branch_id) {
            throw ValidationException::withMessages([
                'location_id' => 'Category must stay under a location in the same branch.',
            ]);
        }

        $category->update([
            'name' => $validated['name'],
            'location_id' => $location->id,
        ]);

        return redirect()->route('categories.all')->with('success', 'Category updated successfully.');
    }

    public function destroyLocation(Request $request, Location $location)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $location->branch_id !== $branchId) {
            abort(403);
        }

        if ($location->categories()->exists()) {
            return redirect()->route('categories.all')->with('error', 'Cannot delete location with existing categories.');
        }

        $location->delete();

        return redirect()->route('categories.all')->with('success', 'Location deleted successfully.');
    }

    public function destroyCategory(Request $request, Category $category)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $branchId = $this->resolveBranchId($request);
        if ($branchId && (int) $category->branch_id !== $branchId) {
            abort(403);
        }

        if ($category->items()->exists()) {
            return redirect()->route('categories.all')->with('error', 'Cannot delete category with existing items.');
        }

        $category->delete();

        return redirect()->route('categories.all')->with('success', 'Category deleted successfully.');
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }
}
