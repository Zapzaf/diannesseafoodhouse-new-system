<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders low stock items on the dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $branch = Branch::create([
        'name' => 'Dashboard Branch',
        'address' => 'Dashboard Address',
        'is_active' => true,
    ]);
    $location = Location::create([
        'name' => 'Dashboard Storage',
        'branch_id' => $branch->id,
    ]);
    $category = Category::create([
        'name' => 'Dashboard Category',
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    Item::create([
        'name' => 'Low Stock Shrimp',
        'sku' => 'DASHBOARD-LOW-STOCK',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 2,
        'low_stock_threshold' => 5,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Low Stock Shrimp')
        ->assertSee('Dashboard Category');
});
