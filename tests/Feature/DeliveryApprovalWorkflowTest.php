<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Fresh branch/item/supplier context for the Delivery BIR & approval
 * workflow tests. Deliberately separate from
 * makeDeliveryProductionContext() in DeliveryProductionExpenseModuleTest —
 * both are plain top-level functions loaded into the same global scope by
 * Pest/PHPUnit, so distinct names avoid a redeclaration clash.
 */
function makeDeliveryApprovalContext(): array
{
    $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => null]);

    $branch = Branch::create([
        'name' => 'Main Branch',
        'address' => 'Main Address',
        'manager_id' => $manager->id,
        'is_active' => true,
    ]);
    $manager->update(['branch_id' => $branch->id]);

    $encoder = User::factory()->create(['role' => 'regular_user', 'branch_id' => $branch->id]);

    $otherBranch = Branch::create([
        'name' => 'Other Branch',
        'address' => 'Other Address',
        'is_active' => true,
    ]);
    $otherManager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $otherBranch->id]);
    $otherBranch->update(['manager_id' => $otherManager->id]);

    $location = Location::create(['name' => 'Storage', 'branch_id' => $branch->id]);
    $category = Category::create([
        'name' => 'Seafood',
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    $item = Item::create([
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

    $supplier = Supplier::create([
        'name' => 'Fish Supplier',
        'tin' => '123-456-789-000',
        'address' => 'Supplier Address',
        'created_by' => $manager->id,
    ]);

    return compact('manager', 'encoder', 'branch', 'otherBranch', 'otherManager', 'item', 'supplier');
}

it('captures BIR/tax fields and computes VAT + EWT when a regular user logs a delivery', function () {
    ['encoder' => $encoder, 'branch' => $branch, 'item' => $item, 'supplier' => $supplier] = makeDeliveryApprovalContext();

    $this->actingAs($encoder)
        ->post(route('deliveries.store'), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'tin' => $supplier->tin,
            'address' => $supplier->address,
            'si_no' => 'SI-0001',
            'amount_w_vat' => 1120,
            'vat_exempt' => 0,
            'non_vat_purchase' => 0,
            'ewt_rate' => '0.01',
            'items' => [[
                'description' => 'Fresh fish',
                'item_id' => $item->id,
                'quantity' => 2,
                'unit' => 'kg',
                'price' => 1120,
                'allocated_to' => 'inventory',
            ]],
        ])
        ->assertRedirect(route('deliveries.index'));

    $delivery = Delivery::query()->firstOrFail();

    expect($delivery->status)->toBe('pending')
        ->and($delivery->tin)->toBe($supplier->tin)
        ->and($delivery->si_no)->toBe('SI-0001')
        ->and((float) $delivery->amount_w_vat)->toBe(1120.0)
        ->and((float) $delivery->net_purchases)->toBe(1000.0)
        ->and((float) $delivery->vat)->toBe(120.0)
        ->and((float) $delivery->ewt_amount)->toBe(10.0);

    // A regular_user's delivery is not auto-approved, so nothing is posted to inventory yet.
    expect((float) $item->fresh()->quantity)->toBe(10.0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('forbids a plain regular_user from approving or rejecting a delivery', function () {
    ['encoder' => $encoder, 'branch' => $branch, 'item' => $item] = makeDeliveryApprovalContext();

    $delivery = Delivery::create([
        'reference_number' => 'DLV-001',
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $encoder->id,
    ]);
    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $item->id,
        'description' => 'Fresh fish',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 200,
        'allocated_to' => 'inventory',
    ]);

    $this->actingAs($encoder)->post(route('deliveries.approve', $delivery), [
        'items' => [['delivery_item_id' => $delivery->items->first()->id, 'allocated_to' => 'inventory']],
    ])->assertForbidden();

    $this->actingAs($encoder)->post(route('deliveries.reject', $delivery), [
        'rejection_remarks' => 'Does not match the receipt.',
    ])->assertForbidden();

    expect($delivery->fresh()->status)->toBe('pending');
});

it('lets a user with the can_approve_deliveries flag approve regardless of branch', function () {
    ['branch' => $branch, 'encoder' => $encoder, 'item' => $item] = makeDeliveryApprovalContext();

    // Designated reviewer (e.g. Jessica) — a regular_user with no branch and no manager role,
    // granted approval rights purely through the flag.
    $approver = User::factory()->create([
        'role' => 'regular_user',
        'branch_id' => null,
        'can_approve_deliveries' => true,
    ]);

    $delivery = Delivery::create([
        'reference_number' => 'DLV-002',
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $encoder->id,
    ]);
    $deliveryItem = DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $item->id,
        'description' => 'Fresh fish',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 200,
        'allocated_to' => 'inventory',
    ]);

    $this->actingAs($approver)->post(route('deliveries.approve', $delivery), [
        'items' => [['delivery_item_id' => $deliveryItem->id, 'allocated_to' => 'inventory']],
    ])->assertRedirect(route('deliveries.index'));

    expect($delivery->fresh()->status)->toBe('received')
        ->and($delivery->fresh()->approved_by)->toBe($approver->id)
        ->and((float) $item->fresh()->quantity)->toBe(12.0);
});

it('lets a designated approver reject a pending delivery with required remarks and posts nothing to inventory', function () {
    ['branch' => $branch, 'encoder' => $encoder, 'item' => $item] = makeDeliveryApprovalContext();

    $approver = User::factory()->create([
        'role' => 'regular_user',
        'branch_id' => null,
        'can_approve_deliveries' => true,
    ]);

    $delivery = Delivery::create([
        'reference_number' => 'DLV-003',
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $encoder->id,
    ]);
    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $item->id,
        'description' => 'Fresh fish',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 200,
        'allocated_to' => 'inventory',
    ]);

    // Remarks required.
    $this->actingAs($approver)->post(route('deliveries.reject', $delivery), [])
        ->assertSessionHasErrors('rejection_remarks');
    expect($delivery->fresh()->status)->toBe('pending');

    $this->actingAs($approver)->post(route('deliveries.reject', $delivery), [
        'rejection_remarks' => 'Quantity does not match the physical receipt.',
    ])->assertRedirect(route('deliveries.index'));

    $delivery->refresh();
    expect($delivery->status)->toBe('rejected')
        ->and($delivery->rejection_remarks)->toBe('Quantity does not match the physical receipt.')
        ->and($delivery->approved_by)->toBe($approver->id)
        ->and((float) $item->fresh()->quantity)->toBe(10.0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('lets the original encoder edit their own pending delivery and recompute VAT/EWT', function () {
    ['encoder' => $encoder, 'branch' => $branch, 'item' => $item, 'supplier' => $supplier] = makeDeliveryApprovalContext();

    $delivery = Delivery::create([
        'reference_number' => 'DLV-004',
        'delivery_date' => now()->toDateString(),
        'supplier_id' => $supplier->id,
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $encoder->id,
        'amount_w_vat' => 560,
        'vat' => 60,
        'net_purchases' => 500,
        'ewt_rate' => 0,
        'ewt_amount' => 0,
    ]);
    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $item->id,
        'description' => 'Fresh fish',
        'quantity' => 1,
        'unit' => 'kg',
        'price' => 560,
        'allocated_to' => 'inventory',
    ]);

    $this->actingAs($encoder)
        ->get(route('deliveries.edit', $delivery))
        ->assertOk();

    $this->actingAs($encoder)
        ->put(route('deliveries.update', $delivery), [
            'supplier_id' => $supplier->id,
            'destination_branch_id' => $branch->id,
            'delivery_date' => now()->toDateString(),
            'tin' => $supplier->tin,
            'address' => $supplier->address,
            'si_no' => 'SI-CORRECTED',
            'amount_w_vat' => 1120,
            'vat_exempt' => 0,
            'non_vat_purchase' => 0,
            'ewt_rate' => '0.02',
            'items' => [[
                'description' => 'Fresh fish (corrected qty)',
                'item_id' => $item->id,
                'quantity' => 2,
                'unit' => 'kg',
                'price' => 1120,
                'allocated_to' => 'inventory',
            ]],
        ])
        ->assertRedirect(route('deliveries.show', $delivery));

    $delivery->refresh();
    expect($delivery->si_no)->toBe('SI-CORRECTED')
        ->and((float) $delivery->net_purchases)->toBe(1000.0)
        ->and((float) $delivery->vat)->toBe(120.0)
        ->and((float) $delivery->ewt_amount)->toBe(20.0)
        ->and($delivery->items()->count())->toBe(1)
        ->and($delivery->items()->first()->description)->toBe('Fresh fish (corrected qty)');
});

it('locks editing once a delivery is approved', function () {
    ['encoder' => $encoder, 'branch' => $branch, 'item' => $item] = makeDeliveryApprovalContext();

    $approver = User::factory()->create([
        'role' => 'regular_user',
        'branch_id' => null,
        'can_approve_deliveries' => true,
    ]);

    $delivery = Delivery::create([
        'reference_number' => 'DLV-005',
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $encoder->id,
    ]);
    $deliveryItem = DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $item->id,
        'description' => 'Fresh fish',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 200,
        'allocated_to' => 'inventory',
    ]);

    $this->actingAs($approver)->post(route('deliveries.approve', $delivery), [
        'items' => [['delivery_item_id' => $deliveryItem->id, 'allocated_to' => 'inventory']],
    ])->assertRedirect(route('deliveries.index'));

    $this->actingAs($encoder)
        ->get(route('deliveries.edit', $delivery))
        ->assertForbidden();
});

it('lets the destination branch manager still approve, unaffected by the new approver flag', function () {
    ['branch' => $branch, 'manager' => $manager, 'encoder' => $encoder, 'item' => $item] = makeDeliveryApprovalContext();

    $delivery = Delivery::create([
        'reference_number' => 'DLV-006',
        'destination_branch_id' => $branch->id,
        'status' => 'pending',
        'created_by' => $encoder->id,
    ]);
    $deliveryItem = DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'item_id' => $item->id,
        'description' => 'Fresh fish',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 200,
        'allocated_to' => 'inventory',
    ]);

    $this->actingAs($manager)->post(route('deliveries.approve', $delivery), [
        'items' => [['delivery_item_id' => $deliveryItem->id, 'allocated_to' => 'inventory']],
    ])->assertRedirect(route('deliveries.index'));

    expect($delivery->fresh()->status)->toBe('received');
});
