<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuOrder;
use App\Models\MenuOrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAdditionalChargeOrderContext(): array
{
    $user = User::factory()->create([
        'role' => 'branch_manager',
        'branch_id' => null,
    ]);

    $branch = Branch::create([
        'name' => 'Charge Test Branch',
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

it('stores multiple additional charges and includes them in menu order totals', function () {
    [$user, $branch, $category, $menuCategory] = makeAdditionalChargeOrderContext();

    $ingredient = Item::create([
        'name' => 'Chicken',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 20,
        'unit_price' => 100,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Fried Chicken',
        'category' => 'Meals',
        'selling_price' => 200,
        'created_by' => $user->id,
    ]);

    $menu->items()->attach($ingredient->id, ['quantity_required' => 1]);

    $response = $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [
            ['menu_id' => $menu->id, 'quantity' => 1],
        ],
        'additional_charges' => [
            ['label' => 'Service Charge', 'type' => 'fixed', 'value' => 50],
            ['label' => 'Delivery Fee', 'type' => 'percentage', 'value' => 10],
        ],
        'regular_pax' => 1,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();

    $response->assertRedirect(route('menu-orders.show', $order));

    expect($order)->not->toBeNull()
        ->and((float) $order->subtotal)->toBe(200.0)
        ->and((float) $order->additional_charge_amount)->toBe(70.0)
        ->and($order->additional_charge_label)->toBe('Multiple Additional Charges')
        ->and($order->additionalChargesList())->toHaveCount(2)
        ->and($order->additionalChargesList()[0]['label'])->toBe('Service Charge')
        ->and((float) $order->additionalChargesList()[0]['amount'])->toBe(50.0)
        ->and($order->additionalChargesList()[1]['type'])->toBe('percentage')
        ->and((float) $order->additionalChargesList()[1]['amount'])->toBe(20.0)
        ->and((float) $order->total_amount)->toBe(270.0);
});

it('copies additional charges into the payment snapshot', function () {
    [$user, $branch, $category, $menuCategory] = makeAdditionalChargeOrderContext();

    $ingredient = Item::create([
        'name' => 'Fish Fillet',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 20,
        'unit_price' => 80,
        'low_stock_threshold' => 0,
        'created_by' => $user->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Fish Meal',
        'category' => 'Meals',
        'selling_price' => 180,
        'created_by' => $user->id,
    ]);

    $menu->items()->attach($ingredient->id, ['quantity_required' => 1]);

    $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'items' => [
            ['menu_id' => $menu->id, 'quantity' => 2],
        ],
        'additional_charges' => [
            ['label' => 'Service Charge', 'type' => 'fixed', 'value' => 40],
            ['label' => 'Handling Fee', 'type' => 'percentage', 'value' => 5],
        ],
        'regular_pax' => 2,
        'pwd_pax' => 0,
        'senior_pax' => 0,
    ]);

    $order = MenuOrder::query()->latest('id')->first();

    $response = $this->actingAs($user)->post(route('menu-orders.payments.store', $order), [
        'amount' => 418,
        'amount_tendered' => 418,
        'method' => 'cash',
        'payment_date' => now()->format('Y-m-d'),
    ]);

    $payment = MenuOrderPayment::query()->latest('id')->first();

    $response->assertRedirect(route('menu-orders.show', $order));

    expect($payment)->not->toBeNull()
        ->and((float) $payment->additional_charge_amount)->toBe(58.0)
        ->and($payment->additionalChargesList())->toHaveCount(2)
        ->and($payment->additionalChargesList()[0]['label'])->toBe('Service Charge')
        ->and((float) $payment->additionalChargesList()[1]['amount'])->toBe(18.0)
        ->and((float) $payment->final_total)->toBe(418.0);
});
