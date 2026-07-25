<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductionInput;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\Supplier;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WastageItem;
use App\Models\WastageReport;
use App\Exports\DeliveryReportExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

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

it('asks admins on all branches to select a delivery branch before logging delivery', function () {
    [, $branch] = makeDeliveryProductionContext();
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);

    $this->actingAs($admin)
        ->withSession(['selected_branch_id' => null])
        ->get(route('deliveries.create'))
        ->assertOk()
        ->assertSee('Delivery Branch')
        ->assertSee('Select Branch')
        ->assertSee('name="destination_branch_id"', false)
        ->assertSee($branch->name);
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

it('reports delivery rows that do not have destinations', function () {
    [$manager, $branch, , , , , $supplier] = makeDeliveryProductionContext();

    $this->actingAs($manager)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'items' => [
                ['description' => 'Fresh tuna', 'quantity' => 2, 'unit' => 'kg'],
                ['description' => 'Fresh salmon', 'quantity' => 1, 'unit' => 'kg'],
            ],
        ])
        ->assertSessionHasErrors([
            'items' => 'The following items do not have a destination selected: Fresh tuna, Fresh salmon. Please select a destination before proceeding.',
        ]);

    $this->assertDatabaseCount('deliveries', 0);
});

it('shows delivery report as item level rows with total delivery cost', function () {
    [$manager, $branch, , $input, , , $supplier] = makeDeliveryProductionContext();

    $delivery = Delivery::create([
        'reference_number' => 'DEL-001',
        'delivery_date' => now()->toDateString(),
        'supplier_id' => $supplier->id,
        'destination_branch_id' => $branch->id,
        'status' => 'received',
        'approved_by' => $manager->id,
        'approved_at' => now(),
        'created_by' => $manager->id,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $input->id,
        'description' => 'Item A',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 500,
        'allocated_to' => 'inventory',
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $input->id,
        'description' => 'Item B',
        'quantity' => 1,
        'unit' => 'kg',
        'price' => 250,
        'allocated_to' => 'inventory',
    ]);

    $response = $this->actingAs($manager)
        ->get(route('reports.delivery.index'))
        ->assertOk()
        ->assertSee('Delivery Reference')
        ->assertSee('Total Delivery Cost')
        ->assertSee('₱750.00')
        ->assertSee('Item A')
        ->assertSee('Item B')
        ->assertDontSee('<th>#</th>', false)
        ->assertDontSee('item(s)');

    expect(substr_count($response->getContent(), 'DEL-001'))->toBe(2);
});

it('exports delivery report as item level rows', function () {
    [$manager, $branch, , $input, , , $supplier] = makeDeliveryProductionContext();
    $date = now()->toDateString();

    $delivery = Delivery::create([
        'reference_number' => 'DEL-EXPORT',
        'delivery_date' => $date,
        'supplier_id' => $supplier->id,
        'destination_branch_id' => $branch->id,
        'status' => 'received',
        'created_by' => $manager->id,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $input->id,
        'description' => 'Exported Item A',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 500,
        'allocated_to' => 'inventory',
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $input->id,
        'description' => 'Exported Item B',
        'quantity' => 1,
        'unit' => 'kg',
        'price' => 250,
        'allocated_to' => 'inventory',
    ]);

    Excel::fake();

    $this->actingAs($manager)
        ->get(route('reports.delivery.export', ['date_from' => $date, 'date_to' => $date]))
        ->assertOk();

    Excel::assertDownloaded("delivery-report-{$date}-to-{$date}.xlsx", function (DeliveryReportExport $export) {
        $rows = $export->collection();

        return $rows->count() === 3
            && $rows[0][0] === 'DEL-EXPORT'
            && $rows[0][4] === 'Exported Item A'
            && $rows[1][0] === 'DEL-EXPORT'
            && $rows[1][4] === 'Exported Item B'
            && $rows[2][4] === 'TOTAL DELIVERY COST'
            && (float) $rows[2][7] === 750.0;
    });
});

it('rejects a delivery inventory destination with a mismatched unit', function () {
    [$manager, $branch, , $input, , , $supplier] = makeDeliveryProductionContext();

    $this->actingAs($manager)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'items' => [[
                'description' => 'Fresh fish by piece',
                'item_id' => $input->id,
                'quantity' => 2,
                'unit' => 'pcs',
                'allocated_to' => 'inventory',
            ]],
        ])
        ->assertSessionHasErrors('items');

    $this->assertDatabaseCount('deliveries', 0);
});

it('stores a custom delivery date and uses it for inventory transaction logs', function () {
    [$manager, $branch, , $input, , , $supplier] = makeDeliveryProductionContext();

    $this->actingAs($manager)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'delivery_date' => '2026-06-01',
            'items' => [[
                'description' => 'Backdated fish delivery',
                'item_id' => $input->id,
                'quantity' => 2,
                'unit' => 'kg',
                'price' => 200,
                'allocated_to' => 'inventory',
            ]],
        ])
        ->assertRedirect(route('deliveries.index'));

    $delivery = Delivery::query()->firstOrFail();
    $transaction = InventoryTransaction::query()->where('item_id', $input->id)->firstOrFail();

    expect($delivery->delivery_date?->toDateString())->toBe('2026-06-01')
        ->and($transaction->transaction_date?->toDateString())->toBe('2026-06-01')
        ->and((float) $input->fresh()->quantity)->toBe(12.0);
});

it('rejects a pending delivery with a mismatched inventory unit during approval', function () {
    [$manager, $branch, , $input] = makeDeliveryProductionContext();

    $delivery = Delivery::create([
        'reference_number' => 'DLV-MISMATCH',
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $manager->id,
    ]);
    $deliveryItem = DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $input->id,
        'description' => 'Fish by piece',
        'quantity' => 2,
        'unit' => 'pcs',
        'allocated_to' => 'inventory',
    ]);

    $this->actingAs($manager)->post(route('deliveries.approve', $delivery), [
        'items' => [[
            'delivery_item_id' => $deliveryItem->id,
            'allocated_to' => 'inventory',
        ]],
    ])->assertSessionHasErrors('items');

    expect($delivery->fresh()->status)->toBe('pending')
        ->and((float) $input->fresh()->quantity)->toBe(10.0);
});

it('creates a separate production order for each delivery item allocated to production', function () {
    [$manager, $branch, , , , , $supplier] = makeDeliveryProductionContext();

    $this->actingAs($manager)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'items' => [
                [
                    'description' => 'Fresh tuna',
                    'quantity' => 2,
                    'unit' => 'kg',
                    'price' => 200,
                    'allocated_to' => 'production',
                ],
                [
                    'description' => 'Fresh salmon',
                    'quantity' => 3,
                    'unit' => 'kg',
                    'price' => 300,
                    'allocated_to' => 'production',
                ],
            ],
        ])
        ->assertRedirect(route('deliveries.index'));

    expect(ProductionOrder::query()->count())->toBe(2)
        ->and(ProductionInput::query()->count())->toBe(2);

    $productionOrders = ProductionOrder::query()
        ->with('inputs.deliveryItem')
        ->orderBy('id')
        ->get();

    expect($productionOrders)->toHaveCount(2)
        ->and($productionOrders->map(fn (ProductionOrder $order): int => $order->inputs->count())->all())
        ->toBe([1, 1])
        ->and($productionOrders->pluck('inputs.0.deliveryItem.description')->all())
        ->toBe(['Fresh tuna', 'Fresh salmon']);
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

it('uses item master units for production inputs and finished outputs', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('productions.store'), [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $input->id,
            'quantity_used' => 1,
            'unit' => 'pcs',
        ]],
    ])->assertRedirect(route('productions.index'));

    $production = ProductionOrder::query()->firstOrFail();
    expect($production->inputs()->firstOrFail()->unit)->toBe('kg');

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 1,
            'unit' => 'pcs',
        ]],
    ])->assertRedirect(route('productions.show', $production));

    expect($production->outputs()->firstOrFail()->unit)->toBe('kg');
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

it('rejects standalone waste conversions to an item from another branch', function () {
    [$manager, $branch, , $input, , $foreignItem] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('productions.store'), [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $input->id,
            'quantity_used' => 1,
        ]],
    ]);

    $production = ProductionOrder::query()->firstOrFail();
    $this->actingAs($manager)->post(route('productions.wastage.store', $production), [
        'items' => [[
            'scrap_name' => 'Fish trim',
            'quantity_lost' => 1,
            'quantity_lost_unit' => 'kg',
            'convert_to_item_id' => $foreignItem->id,
            'converted_quantity' => 1,
        ]],
    ])->assertSessionHasErrors('items');

    expect(WastageReport::query()->count())->toBe(0);
});

it('finishes production with scrap conversion when the input comes from a delivery source item', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $delivery = Delivery::create([
        'reference_number' => 'DLV-PROD-SCRAP',
        'destination_branch_id' => $branch->id,
        'status' => 'received',
        'created_by' => $manager->id,
        'approved_by' => $manager->id,
        'approved_at' => now(),
    ]);

    $deliveryItem = DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => null,
        'source_item_id' => $input->id,
        'description' => 'Raw Fish for production',
        'quantity' => 2,
        'unit' => 'kg',
        'allocated_to' => 'production',
    ]);

    $production = ProductionOrder::create([
        'branch_id' => $branch->id,
        'status' => 'in_progress',
        'created_by' => $manager->id,
    ]);

    ProductionInput::create([
        'production_order_id' => $production->id,
        'item_id' => null,
        'delivery_item_id' => $deliveryItem->id,
        'quantity_used' => 2,
        'unit' => 'kg',
    ]);

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 1,
            'unit' => 'kg',
        ]],
        'wastage' => [[
            'scrap_name' => 'Fish trim',
            'quantity_lost' => 1,
            'quantity_lost_unit' => 'kg',
            'convert_to_item_id' => $output->id,
            'converted_quantity' => 0.5,
        ]],
    ])->assertRedirect(route('productions.show', $production));

    expect($production->fresh()->status)->toBe('finished')
        ->and(WastageReport::query()->count())->toBe(1)
        ->and((float) $input->fresh()->quantity)->toBe(10.0)
        ->and((float) $output->fresh()->quantity)->toBe(1.5);

    $this->assertDatabaseHas('wastage_items', [
        'item_id' => null,
        'scrap_name' => 'Fish trim',
        'quantity_lost' => 1,
        'quantity_lost_unit' => 'kg',
        'convert_to_item_id' => $output->id,
        'converted_quantity' => 0.5,
    ]);
});

it('rejects production scrap conversion without convert qty', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('productions.store'), [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $input->id,
            'quantity_used' => 2,
            'unit' => 'kg',
        ]],
    ]);

    $production = ProductionOrder::query()->firstOrFail();

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 1,
            'unit' => 'kg',
        ]],
        'wastage' => [[
            'scrap_name' => 'Fish trim',
            'quantity_lost' => 1,
            'quantity_lost_unit' => 'kg',
            'convert_to_item_id' => $output->id,
        ]],
    ])->assertSessionHasErrors('wastage.0.converted_quantity');

    expect($production->fresh()->status)->toBe('in_progress')
        ->and(WastageReport::query()->count())->toBe(0)
        ->and((float) $output->fresh()->quantity)->toBe(0.0);
});

it('rejects production scrap conversion without convert to item', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $this->actingAs($manager)->post(route('productions.store'), [
        'branch_id' => $branch->id,
        'inputs' => [[
            'item_id' => $input->id,
            'quantity_used' => 2,
            'unit' => 'kg',
        ]],
    ]);

    $production = ProductionOrder::query()->firstOrFail();

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 1,
            'unit' => 'kg',
        ]],
        'wastage' => [[
            'scrap_name' => 'Fish trim',
            'quantity_lost' => 1,
            'quantity_lost_unit' => 'kg',
            'converted_quantity' => 0.5,
        ]],
    ])->assertSessionHasErrors('wastage.0.convert_to_item_id');

    expect($production->fresh()->status)->toBe('in_progress')
        ->and(WastageReport::query()->count())->toBe(0)
        ->and((float) $output->fresh()->quantity)->toBe(0.0);
});

it('shows one row for each scrap waste item on the scrap list', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $production = ProductionOrder::create([
        'branch_id' => $branch->id,
        'status' => 'finished',
        'created_by' => $manager->id,
    ]);

    $report = WastageReport::create([
        'production_order_id' => $production->id,
        'branch_id' => $branch->id,
        'created_by' => $manager->id,
    ]);

    WastageItem::create([
        'wastage_report_id' => $report->id,
        'item_id' => $input->id,
        'scrap_name' => 'Fish trim',
        'quantity_lost' => 1,
        'quantity_lost_unit' => 'kg',
        'reason' => 'Processing scrap',
    ]);

    WastageItem::create([
        'wastage_report_id' => $report->id,
        'item_id' => null,
        'quantity_lost' => 0.5,
        'quantity_lost_unit' => 'kg',
        'convert_to_item_id' => $output->id,
        'converted_quantity' => 0.5,
    ]);

    $response = $this->actingAs($manager)
        ->get(route('scrap.index'))
        ->assertOk()
        ->assertSee('2 record(s)')
        ->assertSee('Fish trim')
        ->assertSee('Scrap Conversion')
        ->assertSee('Cleaned Fish')
        ->assertDontSee('wasteModal', false);

    expect(substr_count($response->getContent(), '<tr>'))->toBeGreaterThanOrEqual(3);
});

it('shows finished production outputs and scrap details on the production view', function () {
    [$manager, $branch, , $input, $output] = makeDeliveryProductionContext();

    $production = ProductionOrder::create([
        'branch_id' => $branch->id,
        'status' => 'finished',
        'created_by' => $manager->id,
        'finished_at' => now(),
    ]);

    ProductionInput::create([
        'production_order_id' => $production->id,
        'item_id' => $input->id,
        'quantity_used' => 3,
        'unit' => 'kg',
    ]);

    ProductionOutput::create([
        'production_order_id' => $production->id,
        'item_id' => $output->id,
        'quantity_produced' => 2,
        'unit' => 'kg',
        'allocated_to' => 'inventory',
    ]);

    $report = WastageReport::create([
        'production_order_id' => $production->id,
        'branch_id' => $branch->id,
        'created_by' => $manager->id,
    ]);

    WastageItem::create([
        'wastage_report_id' => $report->id,
        'item_id' => $input->id,
        'scrap_name' => 'Fish trim',
        'quantity_lost' => 1,
        'quantity_lost_unit' => 'kg',
        'convert_to_item_id' => $output->id,
        'converted_quantity' => 0.5,
        'reason' => 'Cutting cleanup',
    ]);

    $this->actingAs($manager)
        ->get(route('productions.show', $production))
        ->assertOk()
        ->assertSee('Finished Outputs')
        ->assertSee('Scrap Details')
        ->assertSee('Fish trim')
        ->assertSee('Cutting cleanup')
        ->assertSee('Cleaned Fish');
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

it('rejects a branch transfer when source and destination item units differ', function () {
    [$manager, , $otherBranch, $input, , $destinationItem] = makeDeliveryProductionContext();
    $destinationItem->update(['unit' => 'pcs']);

    $this->actingAs($manager)->post(route('inventory.transfer', $input), [
        'destination_branch_id' => $otherBranch->id,
        'destination_item_id' => $destinationItem->id,
        'quantity' => 2,
    ])->assertSessionHasErrors('destination_item_id');

    $this->assertDatabaseCount('transfers', 0);
});

it('allows equivalent piece and pcs units during a branch transfer', function () {
    [$manager, , $otherBranch, $input, , $destinationItem] = makeDeliveryProductionContext();
    $input->update(['unit' => 'piece']);
    $destinationItem->update(['unit' => 'pcs']);

    $this->actingAs($manager)->post(route('inventory.transfer', $input), [
        'destination_branch_id' => $otherBranch->id,
        'destination_item_id' => $destinationItem->id,
        'quantity' => 2,
    ])->assertRedirect(route('deliveries.index'));

    $this->assertDatabaseCount('transfers', 1);
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

it('cancels an in-progress production order and restocks its inputs', function () {
    [$manager, $branch, , $input] = makeDeliveryProductionContext();

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

    $this->actingAs($manager)->post(route('productions.cancel', $production))
        ->assertRedirect(route('productions.index'));

    expect((float) $input->fresh()->quantity)->toBe(10.0)
        ->and($production->fresh()->status)->toBe('cancelled')
        ->and($production->fresh()->cancelled_at)->not->toBeNull();

    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $input->id,
        'type' => 'in',
        'quantity' => 4,
        'reason' => 'Cancelled Production: PROD-'.$production->id,
        'status' => 'approved',
    ]);
});

it('refuses to cancel a production order that already has outputs', function () {
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

    $this->actingAs($manager)->post(route('productions.finish', $production), [
        'outputs' => [[
            'item_id' => $output->id,
            'quantity_produced' => 3,
            'unit' => 'kg',
        ]],
    ])->assertRedirect(route('productions.show', $production));

    $this->actingAs($manager)->post(route('productions.cancel', $production))
        ->assertSessionHasErrors('production');

    expect($production->fresh()->status)->toBe('finished')
        ->and((float) $input->fresh()->quantity)->toBe(6.0);
});
