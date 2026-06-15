<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\Supplier;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function makeDeliveryProductionContext(): array
{
    $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => null]);

    $branch = Branch::create([
        'name' => 'Main Branch',
        'address' => 'Main Address',
        'manager_id' => $manager->id,
        'is_active' => true,
    ]);
    $manager->update(['branch_id' => $branch->id]);

    $otherBranch = Branch::create([
        'name' => 'Other Branch',
        'address' => 'Other Address',
        'is_active' => true,
    ]);
    $otherManager = User::factory()->create([
        'role' => 'branch_manager',
        'branch_id' => $otherBranch->id,
    ]);
    $otherBranch->update(['manager_id' => $otherManager->id]);

    $location = Location::create(['name' => 'Storage', 'branch_id' => $branch->id]);
    $category = Category::create([
        'name' => 'Seafood',
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    $otherLocation = Location::create(['name' => 'Other Storage', 'branch_id' => $otherBranch->id]);
    $otherCategory = Category::create([
        'name' => 'Other Seafood',
        'location_id' => $otherLocation->id,
        'branch_id' => $otherBranch->id,
    ]);

    $input = Item::create([
        'name' => 'Raw Fish',
        'sku' => 'RAW-FISH',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 100,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    $output = Item::create([
        'name' => 'Cleaned Fish',
        'sku' => 'CLEAN-FISH',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 0,
        'unit_price' => 0,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    $foreignItem = Item::create([
        'name' => 'Foreign Fish',
        'sku' => 'FOREIGN-FISH',
        'category_id' => $otherCategory->id,
        'branch_id' => $otherBranch->id,
        'unit' => 'kg',
        'quantity' => 0,
        'unit_price' => 0,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    $supplier = Supplier::create([
        'name' => 'Fish Supplier',
        'created_by' => $manager->id,
    ]);

    return [$manager->fresh(), $branch, $otherBranch, $input, $output, $foreignItem, $supplier, $otherManager];
}

it('requires an inventory item before storing a delivery', function () {
    [$manager, $branch, , , , , $supplier] = makeDeliveryProductionContext();

    $this->actingAs($manager)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'items' => [[
                'description' => 'Fresh fish',
                'quantity' => 2,
                'unit' => 'kg',
                'price' => 200,
                'allocated_to' => 'inventory',
            ]],
        ])
        ->assertSessionHasErrors('items.0.item_id');

    $this->assertDatabaseCount('deliveries', 0);
});

it('rejects a delivery inventory item from another branch', function () {
    [$manager, $branch, , , , $foreignItem, $supplier] = makeDeliveryProductionContext();

    $this->actingAs($manager)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'items' => [[
                'description' => 'Fresh fish',
                'item_id' => $foreignItem->id,
                'quantity' => 2,
                'unit' => 'kg',
                'price' => 200,
                'allocated_to' => 'inventory',
            ]],
        ])
        ->assertSessionHasErrors('items');

    $this->assertDatabaseCount('deliveries', 0);
});

it('deducts production inputs only once and logs the finished output', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('productions.store'), [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $input->id,
            'quantity_used' => 4,
            'unit' => 'kg',
        ]],
    ])->assertRedirect(route('productions.index'));

    $production = ProductionOrder::query()->firstOrFail();
    expect((float) $input->fresh()->quantity)->toBe(6.0);

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 3,
            'unit' => 'kg',
        ]],
    ])->assertRedirect(route('productions.show', $production));

    expect((float) $input->fresh()->quantity)->toBe(6.0)
        ->and((float) $output->fresh()->quantity)->toBe(3.0)
        ->and($production->fresh()->status)->toBe('finished');

    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $output->id,
        'type' => 'in',
        'quantity' => 3,
        'status' => 'approved',
    ]);
});

it('rejects production outputs from another branch', function () {
    [$manager, $branch, , $input, , $foreignItem] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('productions.store'), [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $input->id,
            'quantity_used' => 1,
            'unit' => 'kg',
        ]],
    ]);

    $production = ProductionOrder::query()->firstOrFail();

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $foreignItem->id,
            'quantity_produced' => 1,
            'unit' => 'kg',
        ]],
    ])->assertSessionHasErrors('outputs');

    expect($production->fresh()->status)->toBe('in_progress');
});

it('validates and completes an inventory branch transfer through delivery approval', function () {
    [$manager, , $otherBranch, $input, , $destinationItem, , $otherManager] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('inventory.transfer', $input), [
        'destination_branch_id' => $otherBranch->id,
        'destination_item_id' => $destinationItem->id,
        'quantity' => 2,
        'reason' => 'Move stock',
    ])->assertRedirect(route('deliveries.index'));

    $transfer = Transfer::query()->firstOrFail();
    $delivery = $transfer->delivery()->with('items')->firstOrFail();

    $this->actingAs($otherManager)->post(route('deliveries.approve', $delivery), [
        'items' => [[
            'delivery_item_id' => $delivery->items->first()->id,
            'allocated_to' => 'inventory',
        ]],
    ])->assertRedirect(route('deliveries.index'));

    expect((float) $input->fresh()->quantity)->toBe(8.0)
        ->and((float) $destinationItem->fresh()->quantity)->toBe(2.0)
        ->and($delivery->fresh()->status)->toBe('received')
        ->and($transfer->fresh()->status)->toBe('approved')
        ->and($transfer->fresh()->approved_by)->toBe($otherManager->id);
});

it('rejects a transfer item that does not belong to the selected destination branch', function () {
    [$manager, , $otherBranch, $input] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('inventory.transfer', $input), [
        'destination_branch_id' => $otherBranch->id,
        'destination_item_id' => $input->id,
        'quantity' => 2,
    ])->assertSessionHasErrors('destination_item_id');

    $this->assertDatabaseCount('transfers', 0);
});

it('rejects a transfer quantity greater than available stock', function () {
    [$manager, , $otherBranch, $input, , $destinationItem] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('inventory.transfer', $input), [
        'destination_branch_id' => $otherBranch->id,
        'destination_item_id' => $destinationItem->id,
        'quantity' => 11,
    ])->assertSessionHasErrors('quantity');

    $this->assertDatabaseCount('transfers', 0);
});

it('exposes expenses as read import and export only', function () {
    expect(Route::has('expenses.index'))->toBeTrue()
        ->and(Route::has('expenses.import'))->toBeTrue()
        ->and(Route::has('expenses.export'))->toBeTrue()
        ->and(Route::has('expenses.show'))->toBeTrue()
        ->and(Route::has('expenses.vatable.store'))->toBeFalse()
        ->and(Route::has('expenses.vatable.update'))->toBeFalse()
        ->and(Route::has('expenses.vatable.destroy'))->toBeFalse()
        ->and(Route::has('expenses.nonvatable.store'))->toBeFalse()
        ->and(Route::has('expenses.disbursement.store'))->toBeFalse();
});
