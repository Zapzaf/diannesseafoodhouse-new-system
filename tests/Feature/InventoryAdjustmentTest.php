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
