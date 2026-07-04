<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->branch = Branch::create(['name' => 'Adjustments', 'address' => 'Test Address', 'is_active' => true]);
    $location = Location::create(['name' => 'Storage', 'branch_id' => $this->branch->id]);
    $this->category = Category::create([
        'name' => 'Supplies',
        'location_id' => $location->id,
        'branch_id' => $this->branch->id,
    ]);
});

function inventoryAdjustmentItem(object $test, string $name, string $unit, float $quantity = 10): Item
{
    return Item::create([
        'name' => $name,
        'sku' => strtoupper(str_replace(' ', '-', $name)),
        'category_id' => $test->category->id,
        'branch_id' => $test->branch->id,
        'unit' => $unit,
        'quantity' => $quantity,
        'low_stock_threshold' => 0,
        'created_by' => $test->user->id,
    ]);
}

it('shows database-compatible units in inventory create and edit dropdowns', function () {
    $item = inventoryAdjustmentItem($this, 'Frozen Shrimp', 'pcs');

    $this->actingAs($this->user)
        ->get(route('inventory.create'))
        ->assertOk()
        ->assertSee('value="pcs"', false)
        ->assertDontSee('value="piece"', false)
        ->assertSee('value="case"', false)
        ->assertSee('value="tank"', false);

    $this->actingAs($this->user)
        ->get(route('inventory.edit', $item))
        ->assertOk()
        ->assertSee('value="pcs" selected', false)
        ->assertDontSee('value="piece"', false);
});

it('renders the optimized transaction item search', function () {
    inventoryAdjustmentItem($this, 'Frozen Shrimp', 'pcs');

    $this->actingAs($this->user)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('MAX_SEARCH_RESULTS = 30', false)
        ->assertSee('Type to search items by name, location, category, or unit.', false)
        ->assertDontSee('Item Model unit price × quantity', false)
        ->assertDontSee('name="items[${idx}][transaction_price]"', false)
        ->assertDontSee('selectedIds.includes(', false);
});

it('uses the item model price for manual inventory transactions', function () {
    $item = inventoryAdjustmentItem($this, 'Priced Shrimp', 'pcs');
    $item->update(['unit_price' => 125]);

    $this->actingAs($this->user)
        ->post(route('transactions.store'), [
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'transaction_price' => 1,
            ]],
            'type' => 'in',
            'reason' => 'Inventory Adjustment / Stock Correction',
        ])
        ->assertRedirect(route('transactions.index'));

    expect((float) InventoryTransaction::query()->firstOrFail()->transaction_price)->toBe(250.0);
});

it('allows deleting inventory items while keeping transaction history visible', function () {
    $item = inventoryAdjustmentItem($this, 'History Shrimp', 'pcs');

    InventoryTransaction::create([
        'item_id' => $item->id,
        'branch_id' => $item->branch_id,
        'type' => 'in',
        'quantity' => 5,
        'beginning_quantity' => 10,
        'remaining_quantity' => 15,
        'transaction_date' => now(),
        'reason' => 'Test history',
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('inventory.destroy', $item))
        ->assertRedirect(route('inventory.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('items', ['id' => $item->id]);
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'reason' => 'Test history',
    ]);

    expect(InventoryTransaction::query()->firstOrFail()->inventory?->name)->toBe('History Shrimp');

    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('History Shrimp')
        ->assertSee('Deleted');
});

it('accepts ajax-style inventory item deletes from the table action button', function () {
    $item = inventoryAdjustmentItem($this, 'Ajax Delete Shrimp', 'pcs');

    $this->actingAs($this->user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->post(route('inventory.destroy', $item), [
            '_method' => 'DELETE',
        ])
        ->assertRedirect(route('inventory.index'));

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});

it('shows reason dropdowns for quick inventory adjustments', function () {
    inventoryAdjustmentItem($this, 'Quick Action Shrimp', 'pcs');

    $this->actingAs($this->user)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('Unit Price')
        ->assertSee('id="stockInReasonSelect"', false)
        ->assertSee('id="deductReasonSelect"', false)
        ->assertSee('<option value="Sales">Sales</option>', false)
        ->assertSee('<option value="Withdrawal">Withdrawal</option>', false)
        ->assertSee('<option value="Others">Others</option>', false)
        ->assertSee('name="custom_reason"', false);
});

it('returns unit price in the inventory table data', function () {
    $item = inventoryAdjustmentItem($this, 'Priced Table Shrimp', 'kg');
    $item->update(['unit_price' => 189.50]);

    $this->actingAs($this->user)
        ->withSession(['selected_branch_id' => $this->branch->id])
        ->getJson(route('inventory.data'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Priced Table Shrimp')
        ->assertJsonPath('data.0.unit_price', '189.5000');
});

it('updates inventory item unit price from the edit form', function () {
    $item = inventoryAdjustmentItem($this, 'Editable Price Shrimp', 'kg');

    $this->actingAs($this->user)
        ->put(route('inventory.update', $item), [
            'name' => $item->name,
            'category_id' => $item->category_id,
            'unit' => $item->unit,
            'unit_price' => '245.75',
            'low_stock_threshold' => $item->low_stock_threshold,
            'notes' => 'Updated item price',
            'sku' => $item->sku,
        ])
        ->assertRedirect(route('inventory.index'))
        ->assertSessionHas('success');

    expect((float) $item->fresh()->unit_price)->toBe(245.75);
});

it('uses the custom others reason for quick inventory adjustments', function () {
    $item = inventoryAdjustmentItem($this, 'Custom Reason Shrimp', 'kg');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/stock-in", [
            'quantity' => '1',
            'reason' => 'Others',
            'custom_reason' => 'Supplier count correction',
        ])
        ->assertRedirect(route('inventory.index'));

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/deduct", [
            'quantity' => '1',
            'reason' => 'Others',
            'custom_reason' => 'Staff meal usage',
        ])
        ->assertRedirect(route('inventory.index'));

    expect(InventoryTransaction::query()->pluck('reason')->all())
        ->toContain('Supplier count correction')
        ->toContain('Staff meal usage');
});

it('requires a custom reason when quick adjustment reason is others', function () {
    $item = inventoryAdjustmentItem($this, 'Missing Custom Reason Shrimp', 'pcs');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/deduct", [
            'quantity' => '1',
            'reason' => 'Others',
        ])
        ->assertSessionHasErrors('custom_reason');

    expect(InventoryTransaction::query()->count())->toBe(0);
});

it('allows decimal stock additions for measurable units', function () {
    $item = inventoryAdjustmentItem($this, 'Cooking Oil', 'liters');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/stock-in", ['quantity' => '1.25'])
        ->assertRedirect(route('inventory.index'))
        ->assertSessionHas('success');

    expect((float) $item->fresh()->quantity)->toBe(11.25)
        ->and((float) InventoryTransaction::query()->value('quantity'))->toBe(1.25)
        ->and(InventoryTransaction::query()->value('status'))->toBe('approved');
});

it('rejects decimal stock additions for count based units', function () {
    $item = inventoryAdjustmentItem($this, 'Bottled Water', 'bottle');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/stock-in", ['quantity' => '1.25'])
        ->assertSessionHasErrors('quantity');

    expect((float) $item->fresh()->quantity)->toBe(10.0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('allows decimal deductions for measurable units and records balances', function () {
    $item = inventoryAdjustmentItem($this, 'Fresh Fish', 'kg');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/deduct", [
            'quantity' => '2.75',
            'reason' => 'Kitchen usage',
        ])
        ->assertRedirect(route('inventory.index'))
        ->assertSessionHas('success');

    $transaction = InventoryTransaction::query()->firstOrFail();

    expect((float) $item->fresh()->quantity)->toBe(7.25)
        ->and((float) $transaction->beginning_quantity)->toBe(10.0)
        ->and((float) $transaction->remaining_quantity)->toBe(7.25);
});

it('rejects decimal or excessive deductions without changing stock', function () {
    $item = inventoryAdjustmentItem($this, 'Food Packs', 'pack');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/deduct", [
            'quantity' => '1.5',
            'reason' => 'Kitchen usage',
        ])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($this->user)
        ->post("/inventory/{$item->id}/deduct", [
            'quantity' => '11',
            'reason' => 'Kitchen usage',
        ])
        ->assertSessionHasErrors('quantity');

    expect((float) $item->fresh()->quantity)->toBe(10.0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});
