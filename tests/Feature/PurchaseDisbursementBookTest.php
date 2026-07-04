<?php

use App\Models\ChartOfAccount;
use App\Models\CheckRegister;
use App\Models\PettyCashVoucher;
use App\Models\PurchaseVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->costAccount = ChartOfAccount::create(['name' => 'Materials & Supplies', 'type' => 'debit_expense']);
    $this->creditAccount = ChartOfAccount::create(['name' => 'Accounts Payable - Test Vendor', 'type' => 'credit_liability']);
    $this->vendor = Supplier::create(['name' => 'Test Vendor', 'created_by' => $this->user->id]);
});

it('renders the check voucher create page with a pcv pre-selected for replenishment', function () {
    $pcv = PettyCashVoucher::create(['date' => now(), 'pcv_no' => 'PCV-RENDER-0001', 'created_by' => $this->user->id]);
    $pcv->items()->create([
        'particulars' => 'Office supplies',
        'cost_account_id' => $this->costAccount->id,
        'amount_w_vat' => 112,
        'vat' => 12,
        'net_purchases' => 100,
    ]);

    $this->actingAs($this->user)
        ->get(route('check-vouchers.create', ['replenish_pcv' => [$pcv->id]]))
        ->assertOk()
        ->assertSee('PCV-RENDER-0001');
});

it('computes net purchases and vat from a vat-inclusive amount when storing an apv item', function () {
    $this->actingAs($this->user)->post(route('purchase-vouchers.store'), [
        'date' => now()->toDateString(),
        'apv_no' => 'APV-0001',
        'vendor_id' => $this->vendor->id,
        'credit_account_id' => $this->creditAccount->id,
        'items' => [
            ['particulars' => 'Cleaning supplies', 'cost_account_id' => $this->costAccount->id, 'amount_w_vat' => 112],
        ],
    ])->assertRedirect(route('purchase-vouchers.index'));

    $item = PurchaseVoucher::firstOrFail()->items->first();

    expect((float) $item->net_purchases)->toBe(100.0)
        ->and((float) $item->vat)->toBe(12.0)
        ->and((float) $item->total_purchases)->toBe(100.0);
});

it('marks an apv paid once a check voucher for the full amount is issued via the check register', function () {
    $apv = PurchaseVoucher::create([
        'date' => now(),
        'apv_no' => 'APV-0002',
        'vendor_id' => $this->vendor->id,
        'credit_account_id' => $this->creditAccount->id,
        'created_by' => $this->user->id,
    ]);
    $apv->items()->create([
        'particulars' => 'Gasoline',
        'cost_account_id' => $this->costAccount->id,
        'amount_w_vat' => 112,
        'vat' => 12,
        'net_purchases' => 100,
    ]);

    expect($apv->fresh()->status)->toBe('unpaid');

    $this->actingAs($this->user)->post(route('check-vouchers.store'), [
        'date' => now()->toDateString(),
        'cv_no' => 'CV-0001',
        'type' => 'apv_payment',
        'particulars' => 'Settle APV-0002',
        'payee_name' => $this->vendor->name,
        'purchase_voucher_id' => $apv->id,
        'amount_w_vat' => 112,
    ])->assertRedirect(route('check-vouchers.index'));

    $checkVoucher = $apv->fresh()->checkVouchers()->firstOrFail();
    expect($checkVoucher->status)->toBe('draft')
        ->and($apv->fresh()->status)->toBe('unpaid');

    $this->actingAs($this->user)->post(route('check-vouchers.issue-check', $checkVoucher), [
        'check_date' => now()->toDateString(),
        'check_no' => 'CHK-0001',
    ])->assertRedirect(route('check-vouchers.show', $checkVoucher));

    expect($checkVoucher->fresh()->status)->toBe('issued')
        ->and($apv->fresh()->status)->toBe('paid');
});

it('rejects a pcf replenishment check voucher whose amount does not match the pcv sub-total', function () {
    $pcv = PettyCashVoucher::create(['date' => now(), 'pcv_no' => 'PCV-0001', 'created_by' => $this->user->id]);
    $pcv->items()->create([
        'particulars' => 'Office supplies',
        'cost_account_id' => $this->costAccount->id,
        'amount_w_vat' => 112,
        'vat' => 12,
        'net_purchases' => 100,
    ]);

    $this->actingAs($this->user)->post(route('check-vouchers.store'), [
        'date' => now()->toDateString(),
        'cv_no' => 'CV-0002',
        'type' => 'pcf_replenishment',
        'particulars' => 'Replenish petty cash',
        'payee_name' => 'Petty Cash Custodian',
        'petty_cash_voucher_ids' => [$pcv->id],
        'amount_w_vat' => 50,
    ])->assertSessionHasErrors('amount_w_vat');

    expect($pcv->fresh()->check_voucher_id)->toBeNull();
});

it('replenishes petty cash vouchers and links them to the check voucher when the amount matches', function () {
    $pcv = PettyCashVoucher::create(['date' => now(), 'pcv_no' => 'PCV-0002', 'created_by' => $this->user->id]);
    $pcv->items()->create([
        'particulars' => 'Office supplies',
        'cost_account_id' => $this->costAccount->id,
        'amount_w_vat' => 112,
        'vat' => 12,
        'net_purchases' => 100,
    ]);

    $this->actingAs($this->user)->post(route('check-vouchers.store'), [
        'date' => now()->toDateString(),
        'cv_no' => 'CV-0003',
        'type' => 'pcf_replenishment',
        'particulars' => 'Replenish petty cash',
        'payee_name' => 'Petty Cash Custodian',
        'petty_cash_voucher_ids' => [$pcv->id],
        'amount_w_vat' => 100,
    ])->assertRedirect(route('check-vouchers.index'));

    expect($pcv->fresh()->check_voucher_id)->not->toBeNull();
});

it('applies ewt on the net purchase amount and computes amount paid', function () {
    $this->actingAs($this->user)->post(route('check-vouchers.store'), [
        'date' => now()->toDateString(),
        'cv_no' => 'CV-0004',
        'type' => 'cod_purchase',
        'particulars' => 'COD delivery',
        'payee_name' => 'Walk-in Supplier',
        'cost_account_id' => $this->costAccount->id,
        'amount_w_vat' => 112,
        'ewt_rate' => 0.02,
    ])->assertRedirect(route('check-vouchers.index'));

    $cv = \App\Models\CheckVoucher::firstOrFail();

    expect((float) $cv->net_purchases)->toBe(100.0)
        ->and((float) $cv->ewt_amount)->toBe(2.0)
        ->and((float) $cv->amount_paid)->toBe(110.0);
});
