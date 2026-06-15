<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\CostingReport;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCostingReportContext(): array
{
    $user = User::factory()->create([
        'role' => 'branch_manager',
        'branch_id' => null,
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => null,
    ]);

    $branch = Branch::create([
        'name' => 'Costing Branch',
        'address' => '123 Costing Ave',
        'manager_id' => $user->id,
        'is_active' => true,
    ]);

    $user->update(['branch_id' => $branch->id]);

    $location = Location::create([
        'name' => 'Kitchen',
        'branch_id' => $branch->id,
    ]);

    $category = Category::create([
        'name' => 'Ingredients',
        'location_id' => $location->id,
        'branch_id' => $branch->id,
    ]);

    $item = Item::create([
        'name' => 'Premium Shrimp',
        'sku' => 'SHRIMP-COSTING',
        'category_id' => $category->id,
        'branch_id' => $branch->id,
        'unit' => 'kg',
        'quantity' => 25,
        'unit_price' => 150,
        'low_stock_threshold' => 5,
        'created_by' => $user->id,
    ]);

    return [$user->fresh(), $admin, $branch, $item];
}

it('submits a costing report without immediately updating the item price', function () {
    [$user, , , $item] = makeCostingReportContext();

    $response = $this->actingAs($user)->post(route('reports.costing.store'), [
        'item_id' => $item->id,
        'proposed_price' => 175.25,
        'reason' => 'Supplier cost increased this week.',
        'costing_details' => 'New supplier invoice and updated markup computation.',
    ]);

    $report = CostingReport::query()->firstOrFail();

    $response->assertRedirect(route('reports.costing.show', $report));
    expect((float) $item->fresh()->unit_price)->toBe(150.0)
        ->and($report->status)->toBe(CostingReport::STATUS_PENDING)
        ->and((float) $report->current_price)->toBe(150.0)
        ->and((float) $report->proposed_price)->toBe(175.25)
        ->and($report->requested_by)->toBe($user->id);
});

it('lets an admin approve a pending costing report and updates the item price', function () {
    [$user, $admin, , $item] = makeCostingReportContext();

    $report = CostingReport::create([
        'branch_id' => $item->branch_id,
        'item_id' => $item->id,
        'current_price' => $item->unit_price,
        'proposed_price' => 188.75,
        'reason' => 'Higher landed inventory cost.',
        'status' => CostingReport::STATUS_PENDING,
        'requested_by' => $user->id,
    ]);

    $response = $this->actingAs($admin)->post(route('reports.costing.approve', $report), [
        'approval_remarks' => 'Approved based on supplier invoice.',
    ]);

    $response->assertRedirect(route('reports.costing.show', $report));

    $report->refresh();
    expect((float) $item->fresh()->unit_price)->toBe(188.75)
        ->and($report->status)->toBe(CostingReport::STATUS_APPROVED)
        ->and($report->approved_by)->toBe($admin->id)
        ->and($report->approved_at)->not->toBeNull()
        ->and($report->approval_remarks)->toBe('Approved based on supplier invoice.');
});

it('lets an admin reject a pending costing report without changing the item price', function () {
    [$user, $admin, , $item] = makeCostingReportContext();

    $report = CostingReport::create([
        'branch_id' => $item->branch_id,
        'item_id' => $item->id,
        'current_price' => $item->unit_price,
        'proposed_price' => 205,
        'reason' => 'Requested markup adjustment.',
        'status' => CostingReport::STATUS_PENDING,
        'requested_by' => $user->id,
    ]);

    $response = $this->actingAs($admin)->post(route('reports.costing.reject', $report), [
        'approval_remarks' => 'Insufficient costing support.',
    ]);

    $response->assertRedirect(route('reports.costing.show', $report));

    $report->refresh();
    expect((float) $item->fresh()->unit_price)->toBe(150.0)
        ->and($report->status)->toBe(CostingReport::STATUS_REJECTED)
        ->and($report->approved_by)->toBe($admin->id)
        ->and($report->approval_remarks)->toBe('Insufficient costing support.');
});

it('prevents non-admin users from approving or rejecting costing reports', function () {
    [$user, , , $item] = makeCostingReportContext();

    $report = CostingReport::create([
        'branch_id' => $item->branch_id,
        'item_id' => $item->id,
        'current_price' => $item->unit_price,
        'proposed_price' => 188.75,
        'reason' => 'Higher landed inventory cost.',
        'status' => CostingReport::STATUS_PENDING,
        'requested_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('reports.costing.approve', $report))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('reports.costing.reject', $report), ['approval_remarks' => 'No'])
        ->assertForbidden();

    expect((float) $item->fresh()->unit_price)->toBe(150.0)
        ->and($report->fresh()->status)->toBe(CostingReport::STATUS_PENDING);
});

it('renders the costing report index, create, and detail screens', function () {
    [$user, , , $item] = makeCostingReportContext();

    $report = CostingReport::create([
        'branch_id' => $item->branch_id,
        'item_id' => $item->id,
        'current_price' => $item->unit_price,
        'proposed_price' => 175,
        'reason' => 'Supplier cost increased.',
        'costing_details' => 'Invoice and updated overhead allocation.',
        'status' => CostingReport::STATUS_PENDING,
        'requested_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.costing.index'))
        ->assertOk()
        ->assertSee('Report History')
        ->assertSee('Premium Shrimp');

    $this->actingAs($user)
        ->get(route('reports.costing.create', ['item_id' => $item->id]))
        ->assertOk()
        ->assertSee('New Costing Report')
        ->assertSee('Premium Shrimp');

    $this->actingAs($user)
        ->get(route('reports.costing.show', $report))
        ->assertOk()
        ->assertSee('Supplier cost increased.');
});
