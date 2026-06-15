<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createInventoryFilterItem(
    User $user,
    Branch $branch,
    Location $location,
    string $categoryName,
    string $itemName,
): Item {
    $category = Category::create([
        'name' => $categoryName,
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    return Item::create([
        'name' => $itemName,
        'sku' => strtoupper(str_replace(' ', '-', $itemName)),
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'low_stock_threshold' => 2,
        'created_by' => $user->id,
    ]);
}

it('renders branch-aware category and subcategory inventory filters', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $branch = Branch::create(['name' => 'Main', 'address' => 'Main Address', 'is_active' => true]);
    $otherBranch = Branch::create(['name' => 'Other', 'address' => 'Other Address', 'is_active' => true]);
    $location = Location::create(['name' => 'Cold Storage', 'branch_id' => $branch->id]);
    $otherLocation = Location::create(['name' => 'Hidden Storage', 'branch_id' => $otherBranch->id]);

    Category::create(['name' => 'Frozen Seafood', 'location_id' => $location->id, 'branch_id' => $branch->id]);
    Category::create(['name' => 'Hidden Category', 'location_id' => $otherLocation->id, 'branch_id' => $otherBranch->id]);

    $this->actingAs($user)
        ->withSession(['selected_branch_id' => $branch->id])
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('All Categories')
        ->assertSee('All Subcategories')
        ->assertSee('Cold Storage')
        ->assertSee('Frozen Seafood')
        ->assertDontSee('Hidden Storage')
        ->assertDontSee('Hidden Category');
});

it('combines inventory location category and search filters', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $branch = Branch::create(['name' => 'Main', 'address' => 'Main Address', 'is_active' => true]);
    $coldStorage = Location::create(['name' => 'Cold Storage', 'branch_id' => $branch->id]);
    $dryStorage = Location::create(['name' => 'Dry Storage', 'branch_id' => $branch->id]);

    $matchingItem = createInventoryFilterItem($user, $branch, $coldStorage, 'Frozen Seafood', 'Premium Shrimp');
    createInventoryFilterItem($user, $branch, $coldStorage, 'Frozen Meat', 'Premium Beef');
    createInventoryFilterItem($user, $branch, $dryStorage, 'Dry Seafood', 'Premium Anchovy');

    $response = $this->actingAs($user)
        ->withSession(['selected_branch_id' => $branch->id])
        ->getJson(route('inventory.data', [
            'location_id' => $coldStorage->id,
            'category_id' => $matchingItem->category_id,
            'search' => 'Shrimp',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Premium Shrimp')
        ->assertJsonPath('data.0.location', 'Cold Storage')
        ->assertJsonPath('data.0.category', 'Frozen Seafood');

    expect($response->json('data'))->toHaveCount(1);
});
