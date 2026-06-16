<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\WasteReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeWasteReportContext(): array
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

    $item = Item::create([
        'name' => 'Fresh Shrimp',
        'sku' => 'FRESH-SHRIMP',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 250,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    $otherItem = Item::create([
        'name' => 'Other Branch Shrimp',
        'sku' => 'OTHER-SHRIMP',
        'category_id' => $otherCategory->id,
        'branch_id' => $otherBranch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 250,
        'low_stock_threshold' => 0,
        'created_by' => $manager->id,
    ]);

    return [$manager->fresh(), $branch, $otherBranch, $item, $otherItem];
}

it('renders the waste report create form', function () {
    [$manager, , , $item] = makeWasteReportContext();

    $this->actingAs($manager)
        ->get(route('waste-reports.create'))
        ->assertOk()
        ->assertSee('Create Waste Report')
        ->assertSee('Fresh Shrimp')
        ->assertSee('Spoilage');
});

it('stores a waste report, deducts inventory, and logs a transaction', function () {
    [$manager, $branch, , $item] = makeWasteReportContext();

    $this->actingAs($manager)->post(route('waste-reports.store'), [
        'branch_id' => $branch->id,
        'report_date' => now()->toDateString(),
        'remarks' => 'End of day waste',
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2.5,
            'reason' => 'Spoilage',
            'notes' => 'Bad odor',
        ]],
    ])->assertRedirect();

    $report = WasteReport::query()->with('items')->firstOrFail();

    expect((float) $item->fresh()->quantity)->toBe(7.5)
        ->and($report->branch_id)->toBe($branch->id)
        ->and($report->items->first()->item_id)->toBe($item->id)
        ->and((float) $report->items->first()->quantity)->toBe(2.5);

    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'branch_id' => $branch->id,
        'type' => 'out',
        'quantity' => 2.5,
        'reason' => 'WASTE REPORT #'.$report->id.': Spoilage',
        'status' => 'approved',
    ]);

    expect((float) InventoryTransaction::query()->firstOrFail()->transaction_price)->toBe(625.0);
});

it('rejects waste quantities greater than available stock', function () {
    [$manager, $branch, , $item] = makeWasteReportContext();

    $this->actingAs($manager)->post(route('waste-reports.store'), [
        'branch_id' => $branch->id,
        'report_date' => now()->toDateString(),
        'items' => [
            ['item_id' => $item->id, 'quantity' => 6, 'reason' => 'Expired'],
            ['item_id' => $item->id, 'quantity' => 5, 'reason' => 'Bad odor'],
        ],
    ])->assertSessionHasErrors('items.0.quantity');

    expect((float) $item->fresh()->quantity)->toBe(10.0)
        ->and(WasteReport::query()->count())->toBe(0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('rejects waste items outside the report branch', function () {
    [$manager, $branch, , , $otherItem] = makeWasteReportContext();

    $this->actingAs($manager)->post(route('waste-reports.store'), [
        'branch_id' => $branch->id,
        'report_date' => now()->toDateString(),
        'items' => [[
            'item_id' => $otherItem->id,
            'quantity' => 1,
            'reason' => 'Damaged',
        ]],
    ])->assertSessionHasErrors('items.0.item_id');

    expect(WasteReport::query()->count())->toBe(0);
});
