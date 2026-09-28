<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('values inventory as of a chosen past date, unwinding later transactions', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $branch = Branch::create(['name' => 'AsOf Branch', 'address' => 'Test Address', 'is_active' => true]);
    $location = Location::create(['name' => 'AsOf Location', 'branch_id' => $branch->id]);
    $category = Category::create(['name' => 'AsOf Category', 'branch_id' => $branch->id, 'location_id' => $location->id]);

    $item = tap(Item::create([
        'name' => 'AsOf Item',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 30,
        'unit_price' => 999, // today's current cost — should NOT be used, a priced 'in' exists before the cutoff
        'low_stock_threshold' => 5,
        'created_by' => $user->id,
    ]), fn ($i) => $i->forceFill(['created_at' => '2026-08-01 00:00:00'])->save());

    // Priced delivery before the cutoff: 20 units @ ₱10 each.
    $before = tap(InventoryTransaction::create([
        'item_id' => $item->id,
        'branch_id' => $branch->id,
        'type' => 'in',
        'quantity' => 20,
        'transaction_price' => 10,
        'status' => 'approved',
        'created_by' => $user->id,
    ]))->forceFill(['created_at' => '2026-09-01 08:00:00'])->save();

    // Another 10 units come in AFTER the cutoff — must be unwound out of the snapshot.
    tap(InventoryTransaction::create([
        'item_id' => $item->id,
        'branch_id' => $branch->id,
        'type' => 'in',
        'quantity' => 10,
        'transaction_price' => 15,
        'status' => 'approved',
        'created_by' => $user->id,
    ]))->forceFill(['created_at' => '2026-09-10 08:00:00'])->save();

    // 5 units go out after the cutoff too — also unwound.
    tap(InventoryTransaction::create([
        'item_id' => $item->id,
        'branch_id' => $branch->id,
        'type' => 'out',
        'quantity' => 5,
        'status' => 'approved',
        'created_by' => $user->id,
    ]))->forceFill(['created_at' => '2026-09-12 08:00:00'])->save();

    // Current balance: 30 (matches 20 + 10 - 5 = 25... item.quantity is set directly
    // to 30 above to represent "today's live balance" independent of these logs,
    // since quantity is a running column, not derived).
    $this->actingAs($user)->get(route('reports.inventory.index', ['as_of_date' => '2026-09-05']))
        ->assertOk()
        ->assertViewHas('snapshotItemsPage', function ($items) {
            $item = $items->getCollection()->firstWhere('name', 'AsOf Item');

            // As of Sep 5: current qty (30) - in-after-cutoff (10) + out-after-cutoff (5) = 25.
            expect((float) $item->quantity_as_of)->toBe(25.0)
                // Cost as of Sep 5: the only priced 'in' on or before that date was ₱10.
                ->and((float) $item->cost_as_of)->toBe(10.0)
                ->and((float) $item->value_as_of)->toBe(250.0);

            return true;
        });
});

it('falls back to the current unit price when an item has no priced purchase before the cutoff', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $branch = Branch::create(['name' => 'AsOf Branch 2', 'address' => 'Test Address', 'is_active' => true]);
    $location = Location::create(['name' => 'AsOf Location 2', 'branch_id' => $branch->id]);
    $category = Category::create(['name' => 'AsOf Category 2', 'branch_id' => $branch->id, 'location_id' => $location->id]);

    $item = tap(Item::create([
        'name' => 'Never Purchased Before Cutoff',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 10,
        'unit_price' => 42,
        'low_stock_threshold' => 5,
        'created_by' => $user->id,
    ]), fn ($i) => $i->forceFill(['created_at' => '2026-08-01 00:00:00'])->save());

    // The only priced 'in' happens AFTER the cutoff — so as-of-cutoff cost has nothing to use.
    tap(InventoryTransaction::create([
        'item_id' => $item->id,
        'branch_id' => $branch->id,
        'type' => 'in',
        'quantity' => 10,
        'transaction_price' => 7,
        'status' => 'approved',
        'created_by' => $user->id,
    ]))->forceFill(['created_at' => '2026-09-10 08:00:00'])->save();

    $this->actingAs($user)->get(route('reports.inventory.index', ['as_of_date' => '2026-09-01']))
        ->assertOk()
        ->assertViewHas('snapshotItemsPage', function ($items) {
            $item = $items->getCollection()->firstWhere('name', 'Never Purchased Before Cutoff');

            expect((float) $item->cost_as_of)->toBe(42.0); // falls back to today's current unit_price

            return true;
        });
});

it('excludes items created after the chosen date', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $branch = Branch::create(['name' => 'AsOf Branch 3', 'address' => 'Test Address', 'is_active' => true]);
    $location = Location::create(['name' => 'AsOf Location 3', 'branch_id' => $branch->id]);
    $category = Category::create(['name' => 'AsOf Category 3', 'branch_id' => $branch->id, 'location_id' => $location->id]);

    $item = tap(Item::create([
        'name' => 'Created Later Item',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 5,
        'unit_price' => 20,
        'low_stock_threshold' => 1,
        'created_by' => $user->id,
    ]))->forceFill(['created_at' => '2026-09-15 08:00:00'])->save();

    $this->actingAs($user)->get(route('reports.inventory.index', ['as_of_date' => '2026-09-01']))
        ->assertOk()
        ->assertViewHas('snapshotItemsPage', function ($items) {
            expect($items->getCollection()->firstWhere('name', 'Created Later Item'))->toBeNull();

            return true;
        });
});
