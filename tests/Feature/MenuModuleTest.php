<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMenuContext(string $branchName = 'North Branch'): array
{
    $user = User::factory()->create([
        'role' => 'branch_manager',
        'branch_id' => null,
    ]);

    $branch = Branch::create([
        'name' => $branchName,
        'address' => '123 Test Ave',
        'manager_id' => $user->id,
        'is_active' => true,
        'vat_enabled' => true,
        'vat_percentage' => 12,
        'pwd_discount_enabled' => true,
        'senior_discount_enabled' => true,
    ]);

    $user->update(['branch_id' => $branch->id]);

    $location = Location::create([
        'name' => 'Kitchen',
        'branch_id' => $branch->id,
    ]);

    $category = Category::create([
        'name' => 'Ingredients',
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    $menuCategory = MenuCategory::create([
        'name' => 'Meals',
        'branch_id' => $branch->id,
    ]);

    return [$user->fresh(), $branch, $location, $category, $menuCategory];
}

it('shows menu categories and ingredients from all branches when admin is on all branches', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => null,
    ]);

    [, $branchA, , $categoryA, $menuCategoryA] = makeMenuContext('Branch A');
    [, $branchB, , $categoryB, $menuCategoryB] = makeMenuContext('Branch B');

    Item::create([
        'name' => 'Milkfish',
        'category_id' => $categoryA->id,
        'branch_id' => $branchA->id,
        'unit' => 'kg',
        'quantity' => 5,
        'unit_price' => 100,
        'low_stock_threshold' => 0,
        'created_by' => $admin->id,
    ]);

    Item::create([
        'name' => 'Prawns',
        'category_id' => $categoryB->id,
        'branch_id' => $branchB->id,
        'unit' => 'kg',
        'quantity' => 0,
        'unit_price' => 150,
        'low_stock_threshold' => 0,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('menus.create'));

    $response
        ->assertOk()
        ->assertSee($menuCategoryA->name)
        ->assertSee($menuCategoryB->name)
        ->assertSee('Milkfish')
        ->assertSee('Prawns');
});

it('creates a menu item successfully even when its ingredient currently has zero stock', function () {
    [$user, $branch, , $category, $menuCategory] = makeMenuContext();

    $ingredient = Item::create([
        'name' => 'Crab Meat',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 0,
        'unit_price' => 220,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('menus.store'), [
        'branch_id' => $branch->id,
        'name' => 'Crab Fried Rice',
        'menu_description' => 'House special',
        'selling_price' => 450,
        'menu_category_id' => $menuCategory->id,
        'ingredients' => [
            [
                'item_id' => $ingredient->id,
                'quantity_required' => 1.25,
            ],
        ],
    ]);

    $menu = Menu::query()->latest('id')->first();

    $response->assertRedirect(route('menus.index'));

    expect($menu)->not->toBeNull()
        ->and((int) $menu->branch_id)->toBe($branch->id)
        ->and((int) $menu->menu_category_id)->toBe($menuCategory->id)
        ->and((int) $menu->category_id)->toBe($category->id)
        ->and($menu->items()->count())->toBe(1)
        ->and((float) $menu->items()->first()->pivot->quantity_required)->toBe(1.25);
});

it('blocks menu creation when ingredients come from a different branch and shows a clear error', function () {
    [$user, $branch, , , $menuCategory] = makeMenuContext('Main Branch');
    [, $otherBranch, , $otherCategory] = makeMenuContext('Other Branch');

    $foreignIngredient = Item::create([
        'name' => 'Imported Squid',
        'category_id' => $otherCategory->id,
        'branch_id' => $otherBranch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 180,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $response = $this->from(route('menus.create'))
        ->actingAs($user)
        ->post(route('menus.store'), [
            'branch_id' => $branch->id,
            'name' => 'Stuffed Squid',
            'selling_price' => 390,
            'menu_category_id' => $menuCategory->id,
            'ingredients' => [
                [
                    'item_id' => $foreignIngredient->id,
                    'quantity_required' => 1,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('menus.create'))
        ->assertSessionHasErrors(['ingredients']);

    expect(Menu::count())->toBe(0);
});
