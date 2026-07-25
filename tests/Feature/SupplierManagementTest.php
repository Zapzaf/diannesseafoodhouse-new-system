<?php

use App\Models\CheckVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('creates a company supplier using the company name as its display name', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'type' => 'company',
        'company_name' => 'La Nueva',
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::firstOrFail();

    expect($supplier->type)->toBe('company')
        ->and($supplier->name)->toBe('La Nueva')
        ->and($supplier->company_name)->toBe('La Nueva')
        ->and($supplier->business_name)->toBeNull()
        ->and($supplier->owner_name)->toBeNull();
});

it('creates a sole proprietorship supplier using the business name as its display name', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'type' => 'sole_proprietorship',
        'business_name' => 'ABC Store',
        'owner_name' => 'Jose B. Rizal',
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::firstOrFail();

    expect($supplier->type)->toBe('sole_proprietorship')
        ->and($supplier->name)->toBe('ABC Store')
        ->and($supplier->business_name)->toBe('ABC Store')
        ->and($supplier->owner_name)->toBe('Jose B. Rizal')
        ->and($supplier->company_name)->toBeNull();
});

it('requires company name when the supplier type is company', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'type' => 'company',
    ])->assertSessionHasErrors('company_name');

    $this->assertDatabaseCount('suppliers', 0);
});

it('requires business name and owner name when the supplier type is sole proprietorship', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'type' => 'sole_proprietorship',
    ])->assertSessionHasErrors(['business_name', 'owner_name']);

    $this->assertDatabaseCount('suppliers', 0);
});

it('requires a supplier type', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'company_name' => 'La Nueva',
    ])->assertSessionHasErrors('type');

    $this->assertDatabaseCount('suppliers', 0);
});

it('updates a supplier and re-derives the display name when the type changes', function () {
    $supplier = Supplier::create([
        'type' => 'company',
        'company_name' => 'La Nueva',
        'name' => 'La Nueva',
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'type' => 'sole_proprietorship',
        'business_name' => 'ABC Store',
        'owner_name' => 'Jose B. Rizal',
    ])->assertRedirect(route('suppliers.index'));

    $supplier->refresh();

    expect($supplier->type)->toBe('sole_proprietorship')
        ->and($supplier->name)->toBe('ABC Store')
        ->and($supplier->owner_name)->toBe('Jose B. Rizal');
});

it('backfills existing suppliers as companies with their prior name preserved', function () {
    $supplier = Supplier::create([
        'name' => 'Legacy Supplier',
        'created_by' => $this->admin->id,
    ])->refresh();

    expect($supplier->type)->toBe('company')
        ->and($supplier->name)->toBe('Legacy Supplier');
});

it('saves the TIN and VAT registration status', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'type' => 'company',
        'company_name' => 'La Nueva',
        'tin' => '123-456-789-000',
        'is_vat_registered' => '1',
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::firstOrFail();

    expect($supplier->tin)->toBe('123-456-789-000')
        ->and($supplier->is_vat_registered)->toBeTrue();
});

it('saves a non-VAT-registered supplier when the checkbox is unchecked', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'type' => 'company',
        'company_name' => 'D Fresco Refilling Station',
        'is_vat_registered' => '0',
    ])->assertRedirect(route('suppliers.index'));

    expect(Supplier::firstOrFail()->is_vat_registered)->toBeFalse();
});

it('shows the supplier profile with its identity, tax, and linked-record details', function () {
    $supplier = Supplier::create([
        'type' => 'sole_proprietorship',
        'business_name' => 'ABC Store',
        'owner_name' => 'Jose B. Rizal',
        'tin' => '123-456-789-000',
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
        ->assertOk()
        ->assertSee('ABC Store')
        ->assertSee('Jose B. Rizal')
        ->assertSee('123-456-789-000');
});

it('deletes a supplier with no purchase or delivery history', function () {
    $supplier = Supplier::create([
        'type' => 'company',
        'company_name' => 'La Nueva',
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect(route('suppliers.index'));

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});

it('refuses to delete a supplier that has existing voucher history', function () {
    $supplier = Supplier::create([
        'type' => 'company',
        'company_name' => 'La Nueva',
        'created_by' => $this->admin->id,
    ]);

    CheckVoucher::create([
        'supplier_id' => $supplier->id,
        'date' => now(),
        'cv_no' => 'CV-TEST-0001',
        'type' => 'other_disbursement',
        'particulars' => 'Test disbursement',
        'payee_name' => 'La Nueva',
    ]);

    $this->actingAs($this->admin)->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

it('only admins can delete a supplier', function () {
    $manager = User::factory()->create(['role' => 'branch_manager']);
    $supplier = Supplier::create([
        'type' => 'company',
        'company_name' => 'La Nueva',
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($manager)->delete(route('suppliers.destroy', $supplier))
        ->assertForbidden();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});
