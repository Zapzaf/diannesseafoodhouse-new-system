<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBranchManagerBranch(): array
{
    $user = User::factory()->create([
        'role' => 'branch_manager',
        'branch_id' => null,
    ]);

    $branch = Branch::create([
        'name' => 'Main Branch',
        'address' => '123 Seafood St.',
        'manager_id' => $user->id,
        'is_active' => true,
        'vat_enabled' => true,
        'vat_percentage' => 12,
        'pwd_discount_enabled' => true,
        'senior_discount_enabled' => true,
    ]);

    $user->update(['branch_id' => $branch->id]);

    return [$user->fresh(), $branch->fresh()];
}

it('allows a branch manager to update branch settings from the settings page', function () {
    [$user, $branch] = makeBranchManagerBranch();

    $response = $this->actingAs($user)->put(route('settings.branch.update'), [
        'branch_id' => $branch->id,
        'vat_enabled' => '1',
        'vat_percentage' => '10',
        'pwd_discount_enabled' => '1',
        'contact_number' => '09171234567',
        'tin_number' => '123-456-789',
    ]);

    $response
        ->assertRedirect(route('settings.show', ['branch_id' => $branch->id]))
        ->assertSessionHas('success');

    $branch->refresh();

    expect($branch->vat_enabled)->toBeTrue()
        ->and((float) $branch->vat_percentage)->toBe(10.0)
        ->and($branch->pwd_discount_enabled)->toBeTrue()
        ->and($branch->senior_discount_enabled)->toBeFalse()
        ->and($branch->contact_number)->toBe('09171234567')
        ->and($branch->tin_number)->toBe('123-456-789');
});

it('does not apply vat or disabled discounts when creating a menu order', function () {
    [$user, $branch] = makeBranchManagerBranch();

    $branch->update([
        'vat_enabled' => false,
        'vat_percentage' => 0,
        'pwd_discount_enabled' => false,
        'senior_discount_enabled' => false,
    ]);

    $location = Location::create([
        'name' => 'Kitchen',
        'branch_id' => $branch->id,
    ]);

    $category = Category::create([
        'name' => 'Meals',
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    $menuCategory = MenuCategory::create([
        'name' => 'Best Sellers',
        'branch_id' => $branch->id,
    ]);

    $menu = Menu::create([
        'branch_id' => $branch->id,
        'menu_category_id' => $menuCategory->id,
        'category_id' => $category->id,
        'name' => 'Grilled Pusit',
        'category' => 'Meals',
        'selling_price' => 100,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('menu-orders.store'), [
        'branch_id' => $branch->id,
        'customer_name' => 'Walk-in',
        'items' => [
            ['menu_id' => $menu->id, 'quantity' => 2],
        ],
        'additional_charge_amount' => 0,
        'regular_pax' => 1,
        'pwd_pax' => 1,
        'senior_pax' => 1,
        'pwd_ids' => ['PWD-001'],
        'pwd_names' => ['Pat Doe'],
        'senior_ids' => ['SC-001'],
        'senior_names' => ['Sam Doe'],
    ]);

    $order = MenuOrder::query()->latest('id')->first();

    $response
        ->assertRedirect(route('menu-orders.show', $order))
        ->assertSessionHas('success');

    expect($order)->not->toBeNull()
        ->and((int) $order->pwd_pax)->toBe(0)
        ->and((int) $order->senior_pax)->toBe(0)
        ->and((float) $order->vat_rate)->toBe(0.0)
        ->and((float) $order->vat_amount)->toBe(0.0)
        ->and((float) $order->discount_amount)->toBe(0.0)
        ->and((float) $order->total_amount)->toBe(200.0)
        ->and($order->discount_id_number)->toBeNull()
        ->and($order->discount_name)->toBeNull();
});
