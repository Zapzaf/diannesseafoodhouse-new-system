<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WasteReport;
use App\Models\WasteReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeApiSecurityContext(): array
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

    $location = Location::create(['name' => 'Main Storage', 'branch_id' => $branch->id]);
    $category = Category::create(['name' => 'Seafood', 'location_id' => $location->id, 'branch_id' => $branch->id]);

    $otherLocation = Location::create(['name' => 'Other Storage', 'branch_id' => $otherBranch->id]);
    $otherCategory = Category::create(['name' => 'Other Seafood', 'location_id' => $otherLocation->id, 'branch_id' => $otherBranch->id]);

    $item = Item::create([
        'name' => 'Raw Fish',
        'sku' => 'RAW-FISH-API',
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
        'sku' => 'CLEAN-FISH-API',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 0,
        'unit_price' => 150,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    $otherItem = Item::create([
        'name' => 'Other Branch Fish',
        'sku' => 'OTHER-FISH-API',
        'category_id' => $otherCategory->id,
        'branch_id' => $otherBranch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 500,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    return [$manager->fresh(), $branch, $otherBranch, $item, $output, $otherItem, $otherCategory];
}

it('prevents api production from using another branch or replaying finish', function () {
    [$manager, $branch, $otherBranch, $item, $output] = makeApiSecurityContext();
    Sanctum::actingAs($manager);

    $this->postJson('/api/productions', [
        'branch_id' => $otherBranch->id,
        'inputs' => [[
            'item_id' => $item->id,
            'quantity_used' => 1,
        ]],
    ])->assertForbidden();

    $this->postJson('/api/productions', [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $item->id,
            'quantity_used' => 2,
            'unit' => 'pcs',
        ]],
    ])->assertCreated();

    $production = ProductionOrder::query()->firstOrFail();
    expect((float) $item->fresh()->quantity)->toBe(8.0)
        ->and($production->inputs()->firstOrFail()->unit)->toBe('kg');

    $this->postJson('/api/productions/'.$production->id.'/finish', [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 1,
            'unit' => 'pcs',
            'allocated_to' => 'inventory',
        ]],
    ])->assertOk();

    $this->postJson('/api/productions/'.$production->id.'/finish', [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 1,
            'allocated_to' => 'inventory',
        ]],
    ])->assertJsonValidationErrors('production');

    expect((float) $item->fresh()->quantity)->toBe(8.0)
        ->and((float) $output->fresh()->quantity)->toBe(1.0);
});

it('scopes api sales to the user branch and uses the item model price', function () {
    [$manager, $branch, , $item, , $otherItem] = makeApiSecurityContext();
    Sanctum::actingAs($manager);

    $this->postJson('/api/sales', [
        'branch_id' => $branch->id,
        'items' => [[
            'item_id' => $otherItem->id,
            'quantity_sold' => 1,
            'unit_price' => 1,
        ]],
    ])->assertJsonValidationErrors('items');

    $this->postJson('/api/sales', [
        'branch_id' => $branch->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity_sold' => 2,
            'unit_price' => 1,
        ]],
    ])->assertCreated();

    $line = SaleItem::query()->firstOrFail();
    expect((float) $line->unit_price)->toBe(100.0)
        ->and((float) $line->subtotal)->toBe(200.0)
        ->and((float) $item->fresh()->quantity)->toBe(8.0);
});

it('validates api transfer source destination and stock rules', function () {
    [$manager, $branch, $otherBranch, $item, , $destinationItem] = makeApiSecurityContext();
    Sanctum::actingAs($manager);

    $this->postJson('/api/transfers', [
        'from_branch_id' => $branch->id,
        'to_branch_id' => $otherBranch->id,
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 1,
            'unit' => 'kg',
        ]],
    ])->assertJsonValidationErrors('items.0.destination_item_id');

    $destinationItem->update(['unit' => 'pcs']);
    $this->postJson('/api/transfers', [
        'from_branch_id' => $branch->id,
        'to_branch_id' => $otherBranch->id,
        'items' => [[
            'item_id' => $item->id,
            'destination_item_id' => $destinationItem->id,
            'quantity' => 1,
            'unit' => 'kg',
        ]],
    ])->assertJsonValidationErrors('items.0.destination_item_id');

    $destinationItem->update(['unit' => 'kg']);
    $this->postJson('/api/transfers', [
        'from_branch_id' => $branch->id,
        'to_branch_id' => $otherBranch->id,
        'items' => [[
            'item_id' => $item->id,
            'destination_item_id' => $destinationItem->id,
            'quantity' => 11,
            'unit' => 'kg',
        ]],
    ])->assertJsonValidationErrors('items.0.quantity');
});

it('keeps waste report search scoped to the user branch', function () {
    [$manager, $branch, $otherBranch, $item, , $otherItem] = makeApiSecurityContext();

    $ownReport = WasteReport::create([
        'branch_id' => $branch->id,
        'report_date' => now()->toDateString(),
        'created_by' => $manager->id,
    ]);
    WasteReportItem::create([
        'waste_report_id' => $ownReport->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'reason' => 'Spoilage',
    ]);

    $otherReport = WasteReport::create([
        'branch_id' => $otherBranch->id,
        'report_date' => now()->toDateString(),
        'created_by' => $manager->id,
    ]);
    WasteReportItem::create([
        'waste_report_id' => $otherReport->id,
        'item_id' => $otherItem->id,
        'quantity' => 1,
        'reason' => 'Spoilage',
    ]);

    $this->actingAs($manager)
        ->get(route('waste-reports.index', ['search' => 'Spoilage']))
        ->assertOk()
        ->assertSee('WR-'.str_pad((string) $ownReport->id, 5, '0', STR_PAD_LEFT))
        ->assertDontSee('WR-'.str_pad((string) $otherReport->id, 5, '0', STR_PAD_LEFT));
});
