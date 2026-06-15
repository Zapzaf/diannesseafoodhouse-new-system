<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMenuOrderContext(): array
{
    $user = User::factory()->create([
        'role' => 'branch_manager',
        'branch_id' => null,
    ]);

    $branch = Branch::create([
        'name' => 'North Branch',
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

    return [$user->fresh(), $branch, $category, $menuCategory];
}

function createSimpleMenuOrderForCompletion(User $user, Branch $branch, Category $category, MenuCategory $menuCategory): MenuOrder
{
    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Completion Test Meal',
        'category' => 'Meals',
        'selling_price' => 500,
        'created_by' => $user->id,
    ]);

    test()->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        'regular_pax' => 1,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    return MenuOrder::query()->latest('id')->firstOrFail();
}

it('prevents menu order creation when inventory is insufficient', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();

    $ingredient = Item::create([
        'name' => 'Squid',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 1,
        'unit_price' => 100,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Calamares',
        'category' => 'Meals',
        'selling_price' => 250,
        'created_by' => $user->id,
    ]);

    $menu->items()->attach($ingredient->id, ['quantity_required' => 2]);

    $response = $this->from(route('menu-orders.create'))
        ->actingAs($user)
        ->post(route('menu-orders.store'), [
            'branch_id' => $branch->id,
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
            'regular_pax' => 1,
            'pwd_pax' => 0,
            'senior_pax' => 0,
        ]);

    $response->assertRedirect(route('menu-orders.create'));
    $response->assertSessionHasErrors();

    expect(MenuOrder::count())->toBe(0)
        ->and(InventoryTransaction::count())->toBe(0)
        ->and((float) $ingredient->fresh()->quantity)->toBe(1.0);
});

it('creates inventory transactions and deducts ingredients when menu order succeeds', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();

    $ingredient = Item::create([
        'name' => 'Shrimp',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 200,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Butter Garlic Shrimp',
        'category' => 'Meals',
        'selling_price' => 320,
        'created_by' => $user->id,
    ]);

    $menu->items()->attach($ingredient->id, ['quantity_required' => 1.5]);

    $response = $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'customer_name' => 'Test Customer',
        'items' => [
            ['menu_id' => $menu->id, 'quantity' => 2],
        ],
        'regular_pax' => 2,
        'pwd_pax' => 0,
        'senior_pax' => 0,
        'additional_charge_amount' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();

    $response->assertRedirect(route('menu-orders.show', $order));

    expect($order)->not->toBeNull()
        ->and($order->orderNumber())->toMatch('/^SALES-BR' . $branch->id . '-\d{8}-\d{6}$/')
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()->inventory_deducted)->toBeTrue()
        ->and((float) $ingredient->fresh()->quantity)->toBe(7.0)
        ->and(InventoryTransaction::count())->toBe(1);

    $this->assertDatabaseHas('menu_orders', [
        'id' => $order->id,
        'order_number' => $order->orderNumber(),
    ]);

    $transaction = InventoryTransaction::query()->first();

    expect((float) $transaction->quantity)->toBe(3.0)
        ->and($transaction->type)->toBe('out')
        ->and((int) $transaction->branch_id)->toBe($branch->id)
        ->and($transaction->log_id)->toMatch('/^TRANS-\d{8}-[A-Z0-9]{6}$/');
});

it('adds menu order items from the order details page without replenishing existing deductions', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();

    $ingredient = Item::create([
        'name' => 'Crab',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 300,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Steamed Crab',
        'category' => 'Meals',
        'selling_price' => 500,
        'created_by' => $user->id,
    ]);
    $menu->items()->attach($ingredient->id, ['quantity_required' => 1]);

    $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        'regular_pax' => 1,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();

    $response = $this->actingAs($user)->post(route('menu-orders.items.store', $order), [
        'items' => [
            ['menu_id' => $menu->id, 'quantity' => 2],
        ],
    ]);

    $response->assertRedirect(route('menu-orders.show', $order));

    expect($order->fresh()->items()->count())->toBe(2)
        ->and((float) $ingredient->fresh()->quantity)->toBe(7.0)
        ->and(InventoryTransaction::query()->where('type', 'in')->count())->toBe(0)
        ->and(InventoryTransaction::query()->where('type', 'out')->count())->toBe(2);
});

it('does not add or change menu order items through the edit update action', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();

    $ingredient = Item::create([
        'name' => 'Lobster',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 300,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Lobster Thermidor',
        'category' => 'Meals',
        'selling_price' => 900,
        'created_by' => $user->id,
    ]);
    $menu->items()->attach($ingredient->id, ['quantity_required' => 1]);

    $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        'regular_pax' => 1,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();
    $existingItem = $order->items()->first();

    $response = $this->actingAs($user)->put(route('menu-orders.update', $order), [
        'branch_id' => $branch->id,
        'customer_name' => 'Updated Customer',
        'items' => [
            ['id' => $existingItem->id, 'menu_id' => $menu->id, 'quantity' => 99],
            ['menu_id' => $menu->id, 'quantity' => 2],
        ],
        'regular_pax' => 2,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $response->assertRedirect(route('menu-orders.show', $order));

    expect($order->fresh()->customer_name)->toBe('Updated Customer')
        ->and($order->fresh()->items()->count())->toBe(1)
        ->and($order->fresh()->items()->first()->quantity)->toBe(1)
        ->and((float) $ingredient->fresh()->quantity)->toBe(9.0)
        ->and(InventoryTransaction::query()->where('type', 'in')->count())->toBe(0)
        ->and(InventoryTransaction::query()->where('type', 'out')->count())->toBe(1);
});

it('lets admins delete a menu order item and replenishes that item inventory', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();

    $admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $branch->id,
    ]);

    $ingredient = Item::create([
        'name' => 'Fish',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 180,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Grilled Fish',
        'category' => 'Meals',
        'selling_price' => 380,
        'created_by' => $user->id,
    ]);
    $menu->items()->attach($ingredient->id, ['quantity_required' => 2]);

    $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [
            ['menu_id' => $menu->id, 'quantity' => 1],
            ['menu_id' => $menu->id, 'quantity' => 1],
        ],
        'regular_pax' => 1,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();
    $itemToDelete = $order->items()->first();

    $response = $this->actingAs($admin)->delete(route('menu-orders.items.destroy', [$order, $itemToDelete]));

    $response->assertRedirect(route('menu-orders.show', $order));

    $replenish = InventoryTransaction::query()->latest('id')->first();

    expect($order->fresh()->items()->count())->toBe(1)
        ->and((float) $ingredient->fresh()->quantity)->toBe(8.0)
        ->and($replenish->type)->toBe('in')
        ->and($replenish->reason)->toBe('Deleted Menu Order Item');
});

it('does not show a manual complete action on the menu order details page', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();
    $order = createSimpleMenuOrderForCompletion($user, $branch, $category, $menuCategory);

    $response = $this->actingAs($user)->get(route('menu-orders.show', $order));

    $response->assertOk()
        ->assertDontSee('Mark this order as completed')
        ->assertDontSee('menu-orders.complete')
        ->assertDontSee('Complete</button>', false);
});

it('keeps a menu order open when a partial payment is recorded', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();
    $order = createSimpleMenuOrderForCompletion($user, $branch, $category, $menuCategory);

    $response = $this->actingAs($user)->post(route('menu-orders.payments.store', $order), [
        'amount' => 200,
        'amount_tendered' => 200,
        'method' => 'cash',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertRedirect(route('menu-orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe('open')
        ->and($order->payment_status)->toBe('partial')
        ->and((float) $order->amount_paid)->toBe(200.0)
        ->and((float) $order->balance)->toBe(round((float) $order->total_amount - 200, 2));
});

it('automatically completes a menu order when a full payment is recorded', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();
    $order = createSimpleMenuOrderForCompletion($user, $branch, $category, $menuCategory);

    $response = $this->actingAs($user)->post(route('menu-orders.payments.store', $order), [
        'amount' => $order->total_amount,
        'amount_tendered' => $order->total_amount,
        'method' => 'cash',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertRedirect(route('menu-orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid')
        ->and((float) $order->amount_paid)->toBe((float) $order->total_amount)
        ->and((float) $order->balance)->toBe(0.0);
});

it('automatically completes a menu order when full payment is recorded from the payments module', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();
    $order = createSimpleMenuOrderForCompletion($user, $branch, $category, $menuCategory);

    $response = $this->actingAs($user)->post(route('payments.store'), [
        'menu_order_id' => $order->id,
        'amount' => $order->total_amount,
        'method' => 'cash',
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertRedirect(route('menu-orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid')
        ->and((float) $order->amount_paid)->toBe((float) $order->total_amount)
        ->and((float) $order->balance)->toBe(0.0);
});

it('renders the 80mm billing print page for a menu order', function () {
    [$user, $branch, $category, $menuCategory] = makeMenuOrderContext();

    $ingredient = Item::create([
        'name' => 'Rice',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 60,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Garlic Rice',
        'category' => 'Sides',
        'selling_price' => 80,
        'created_by' => $user->id,
    ]);
    $menu->items()->attach($ingredient->id, ['quantity_required' => 0.25]);

    $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        'regular_pax' => 1,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();

    $this->actingAs($user)
        ->get(route('menu-orders.billing', $order))
        ->assertOk()
        ->assertSee('BILLING STATEMENT')
        ->assertSee('NOT AN OFFICIAL RECEIPT');
});
