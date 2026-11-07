<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseVoucher;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DeliveryDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeDeliveryApvContext(): array
{
    $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => null]);
    $branch = Branch::create(['name' => 'Main Branch', 'address' => 'Main', 'manager_id' => $manager->id, 'is_active' => true]);
    $manager->update(['branch_id' => $branch->id]);
    $encoder = User::factory()->create(['role' => 'regular_user', 'branch_id' => $branch->id]);

    $location = Location::create(['name' => 'Storage', 'branch_id' => $branch->id]);
    $category = Category::create(['name' => 'Seafood', 'location_id' => $location->id, 'branch_id' => $branch->id]);
    $item = Item::create([
        'name' => 'Raw Fish', 'sku' => 'RAW-FISH', 'category_id' => $category->id, 'branch_id' => $branch->id,
        'unit' => 'kg', 'quantity' => 10, 'unit_price' => 100, 'low_stock_threshold' => 0, 'created_by' => $manager->id,
    ]);
    $supplier = Supplier::create(['name' => 'Fish Supplier', 'tin' => '123', 'address' => 'Addr', 'created_by' => $manager->id]);

    return compact('manager', 'encoder', 'branch', 'item', 'supplier');
}

function makePendingSupplierDelivery(array $ctx, array $overrides = [], array $items = [[200, 2]]): Delivery
{
    $delivery = Delivery::create([
        'reference_number' => 'DLV-APV-'.random_int(1000, 9999),
        'delivery_date' => '2026-11-01',
        'supplier_id' => $ctx['supplier']->id,
        'destination_branch_id' => $ctx['branch']->id,
        'status' => 'pending',
        'created_by' => $ctx['encoder']->id,
        'si_no' => 'SI-100',
        ...$overrides,
    ]);

    foreach ($items as $i => [$price, $qty]) {
        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'item_id' => $ctx['item']->id,
            'description' => 'Fish line '.($i + 1),
            'quantity' => $qty,
            'unit' => 'kg',
            'price' => $price,
            'allocated_to' => 'inventory',
        ]);
    }

    return $delivery->load('items');
}

function approveDelivery($test, User $user, Delivery $delivery)
{
    return $test->actingAs($user)->post(route('deliveries.approve', $delivery), [
        'items' => $delivery->items->map(fn ($i) => ['delivery_item_id' => $i->id, 'allocated_to' => 'inventory'])->all(),
    ]);
}

it('creates an unpaid credit APV matching the delivery invoice when approved', function () {
    $ctx = makeDeliveryApvContext();
    $delivery = makePendingSupplierDelivery($ctx, [
        'amount_w_vat' => 1120, 'vat' => 120, 'net_purchases' => 1000, 'vat_exempt' => 50, 'non_vat_purchase' => 0,
    ], [[300, 1], [100, 2]]);

    approveDelivery($this, $ctx['manager'], $delivery)->assertRedirect(route('deliveries.index'));

    $apv = PurchaseVoucher::with('items', 'creditAccount')->sole();

    expect($apv->delivery_id)->toBe($delivery->id)
        ->and($apv->vendor_id)->toBe($ctx['supplier']->id)
        ->and($apv->branch_id)->toBe($ctx['branch']->id)
        ->and($apv->si_no)->toBe('SI-100')
        ->and($apv->purchase_type)->toBe('credit')
        ->and($apv->status)->toBe('unpaid')
        ->and($apv->date->toDateString())->toBe('2026-11-01')
        ->and($apv->buyer)->toBe($ctx['encoder']->name)
        ->and($apv->creditAccount->name)->toBe('Accounts Payable - Trade')
        ->and($apv->items)->toHaveCount(2)
        ->and($apv->payable_total)->toBe(1170.0)
        // Weighted 3:1 by line price.
        ->and((float) $apv->items[0]->amount_w_vat)->toBe(840.0)
        ->and((float) $apv->items[1]->amount_w_vat)->toBe(280.0)
        ->and((float) $apv->items[0]->net_purchases + (float) $apv->items[1]->net_purchases)->toBe(1000.0);
});

it('falls back to item prices when the delivery has no invoice amounts', function () {
    $ctx = makeDeliveryApvContext();
    $delivery = makePendingSupplierDelivery($ctx, [], [[100, 1], [100, 1], [100.01, 1]]);

    approveDelivery($this, $ctx['manager'], $delivery);

    $apv = PurchaseVoucher::with('items')->sole();
    expect($apv->payable_total)->toBe(300.01)
        ->and((float) $apv->items->sum('amount_w_vat'))->toBe(300.01);
});

it('does not create an APV for a delivery with nothing payable', function () {
    $ctx = makeDeliveryApvContext();
    $delivery = makePendingSupplierDelivery($ctx, [], [[0, 2]]);

    approveDelivery($this, $ctx['manager'], $delivery);

    expect($delivery->fresh()->status)->toBe('received')
        ->and(PurchaseVoucher::count())->toBe(0);
});

it('does not create an APV for a branch transfer', function () {
    $ctx = makeDeliveryApvContext();
    $source = Branch::create(['name' => 'Source', 'address' => 'S', 'is_active' => true]);
    $delivery = makePendingSupplierDelivery($ctx, ['supplier_id' => null, 'source_branch_id' => $source->id]);

    app(\App\Services\DeliveryApvService::class)->createForDelivery($delivery, $ctx['manager']->id);

    expect(PurchaseVoucher::count())->toBe(0);
});

it('creates the APV when a branch manager logs an auto-approved delivery', function () {
    $ctx = makeDeliveryApvContext();

    $this->actingAs($ctx['manager'])->post(route('deliveries.store'), [
        'supplier_id' => $ctx['supplier']->id,
        'destination_branch_id' => $ctx['branch']->id,
        'si_no' => 'SI-200',
        'amount_w_vat' => 560,
        'items' => [[
            'description' => 'Fresh fish', 'item_id' => $ctx['item']->id, 'quantity' => 2,
            'unit' => 'kg', 'price' => 560, 'allocated_to' => 'inventory',
        ]],
    ])->assertRedirect(route('deliveries.index'));

    $apv = PurchaseVoucher::sole();
    expect($apv->delivery_id)->toBe(Delivery::sole()->id)
        ->and($apv->si_no)->toBe('SI-200')
        ->and($apv->payable_total)->toBe(560.0);
});

it('uses an existing chart of account instead of creating a duplicate', function () {
    $ctx = makeDeliveryApvContext();
    $existing = ChartOfAccount::create(['name' => 'Accounts Payable - Trade', 'type' => 'credit_liability', 'is_active' => true]);
    $delivery = makePendingSupplierDelivery($ctx, ['amount_w_vat' => 200]);

    approveDelivery($this, $ctx['manager'], $delivery);

    expect(PurchaseVoucher::sole()->credit_account_id)->toBe($existing->id)
        ->and(ChartOfAccount::where('type', 'credit_liability')->count())->toBe(1);
});

it('deletes the unpaid APV together with its delivery', function () {
    $ctx = makeDeliveryApvContext();
    $delivery = makePendingSupplierDelivery($ctx, ['amount_w_vat' => 200]);
    approveDelivery($this, $ctx['manager'], $delivery);

    app(DeliveryDeletionService::class)->delete($delivery->fresh(), $ctx['manager']->id);

    expect(Delivery::count())->toBe(0)->and(PurchaseVoucher::count())->toBe(0);
});

it('blocks deleting a delivery whose APV already has payments', function () {
    $ctx = makeDeliveryApvContext();
    $delivery = makePendingSupplierDelivery($ctx, ['amount_w_vat' => 200]);
    approveDelivery($this, $ctx['manager'], $delivery);
    PurchaseVoucher::sole()->update(['status' => 'partially_paid']);

    expect(fn () => app(DeliveryDeletionService::class)->delete($delivery->fresh(), $ctx['manager']->id))
        ->toThrow(ValidationException::class);

    expect(Delivery::count())->toBe(1)->and(PurchaseVoucher::count())->toBe(1);
});
