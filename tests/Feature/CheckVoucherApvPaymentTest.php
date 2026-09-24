<?php

use App\Models\ChartOfAccount;
use App\Models\CheckVoucher;
use App\Models\PurchaseVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->costAccount = ChartOfAccount::create(['name' => 'Multi APV Cost Account', 'type' => 'debit_expense']);
    $this->creditAccount = ChartOfAccount::create(['name' => 'Multi APV Payable', 'type' => 'credit_liability']);
    $this->vendorA = Supplier::create(['name' => 'Vendor A', 'created_by' => $this->user->id]);
    $this->vendorB = Supplier::create(['name' => 'Vendor B', 'created_by' => $this->user->id]);

    $this->makeApv = function (string $no, Supplier $vendor, float $amount = 112): PurchaseVoucher {
        $apv = PurchaseVoucher::create([
            'date' => now(),
            'apv_no' => $no,
            'vendor_id' => $vendor->id,
            'credit_account_id' => $this->creditAccount->id,
            'created_by' => $this->user->id,
        ]);
        $apv->items()->create([
            'particulars' => 'Item',
            'cost_account_id' => $this->costAccount->id,
            'amount_w_vat' => $amount,
            'vat' => round($amount / 1.12 * 0.12, 2),
            'net_purchases' => round($amount / 1.12, 2),
        ]);

        return $apv;
    };

    $this->storeCv = fn (array $allocations, string $cvNo = 'CV-M-0001') => $this->actingAs($this->user)->post(route('check-vouchers.store'), [
        'date' => now()->toDateString(),
        'cv_no' => $cvNo,
        'type' => 'apv_payment',
        'particulars' => 'Multi APV payment',
        'payee_name' => 'Vendor A',
        'payment_method' => 'bank_transfer',
        'apv_allocations' => $allocations,
    ]);
});

it('creates one check voucher that pays several apvs from different vendors', function () {
    $apv1 = ($this->makeApv)('APV-M-1', $this->vendorA, 112);
    $apv2 = ($this->makeApv)('APV-M-2', $this->vendorB, 224);

    ($this->storeCv)([
        ['purchase_voucher_id' => $apv1->id, 'amount_w_vat' => 112],
        ['purchase_voucher_id' => $apv2->id, 'amount_w_vat' => 100],
    ])->assertRedirect(route('check-vouchers.index'));

    $cv = CheckVoucher::firstOrFail();

    expect((float) $cv->amount_w_vat)->toBe(212.0)
        ->and($cv->purchase_voucher_id)->toBeNull()
        ->and($cv->apvAllocations)->toHaveCount(2);
});

it('counts only each apv\'s own share of a multi-apv check voucher as paid', function () {
    $apv1 = ($this->makeApv)('APV-M-3', $this->vendorA, 112);
    $apv2 = ($this->makeApv)('APV-M-4', $this->vendorB, 224);

    ($this->storeCv)([
        ['purchase_voucher_id' => $apv1->id, 'amount_w_vat' => 112],
        ['purchase_voucher_id' => $apv2->id, 'amount_w_vat' => 100],
    ]);

    $cv = CheckVoucher::firstOrFail();
    $this->actingAs($this->user)->post(route('check-vouchers.mark-paid', $cv))->assertRedirect();

    expect((float) $apv1->fresh()->amount_paid)->toBe(112.0)
        ->and($apv1->fresh()->status)->toBe('paid')
        ->and((float) $apv2->fresh()->amount_paid)->toBe(100.0)
        ->and($apv2->fresh()->status)->toBe('partially_paid');
});

it('allows the same apv on two lines but rejects a combined amount over its balance', function () {
    $apv = ($this->makeApv)('APV-M-5', $this->vendorA, 112);

    ($this->storeCv)([
        ['purchase_voucher_id' => $apv->id, 'amount_w_vat' => 60],
        ['purchase_voucher_id' => $apv->id, 'amount_w_vat' => 52],
    ])->assertRedirect(route('check-vouchers.index'));

    expect(CheckVoucher::firstOrFail()->apvAllocations)->toHaveCount(2);

    $other = ($this->makeApv)('APV-M-6', $this->vendorA, 112);

    ($this->storeCv)([
        ['purchase_voucher_id' => $other->id, 'amount_w_vat' => 60],
        ['purchase_voucher_id' => $other->id, 'amount_w_vat' => 60],
    ], 'CV-M-0002')->assertSessionHasErrors('apv_allocations');
});

it('adds, edits and removes apv allocations on a saved check voucher and recomputes every apv', function () {
    $apv1 = ($this->makeApv)('APV-M-7', $this->vendorA, 112);
    $apv2 = ($this->makeApv)('APV-M-8', $this->vendorB, 112);

    ($this->storeCv)([['purchase_voucher_id' => $apv1->id, 'amount_w_vat' => 112]]);
    $cv = CheckVoucher::firstOrFail();
    $this->actingAs($this->user)->post(route('check-vouchers.mark-paid', $cv));

    expect($apv1->fresh()->status)->toBe('paid');

    $this->actingAs($this->user)->post(route('check-vouchers.apv-allocations.store', $cv), [
        'purchase_voucher_id' => $apv2->id,
        'amount_w_vat' => 50,
    ])->assertSessionHasNoErrors();

    expect((float) $cv->fresh()->amount_w_vat)->toBe(162.0)
        ->and($apv2->fresh()->status)->toBe('partially_paid');

    $allocation = $cv->apvAllocations()->where('purchase_voucher_id', $apv2->id)->firstOrFail();

    $this->actingAs($this->user)->put(route('check-vouchers.apv-allocations.update', [$cv, $allocation]), [
        'purchase_voucher_id' => $apv2->id,
        'amount_w_vat' => 112,
    ])->assertSessionHasNoErrors();

    expect($apv2->fresh()->status)->toBe('paid');

    $this->actingAs($this->user)->delete(route('check-vouchers.apv-allocations.destroy', [$cv, $allocation]));

    expect($apv2->fresh()->status)->toBe('unpaid')
        ->and((float) $cv->fresh()->amount_w_vat)->toBe(112.0);
});

it('reverts every linked apv when a multi-apv check voucher is deleted', function () {
    $apv1 = ($this->makeApv)('APV-M-9', $this->vendorA, 112);
    $apv2 = ($this->makeApv)('APV-M-10', $this->vendorB, 112);

    ($this->storeCv)([
        ['purchase_voucher_id' => $apv1->id, 'amount_w_vat' => 112],
        ['purchase_voucher_id' => $apv2->id, 'amount_w_vat' => 112],
    ]);
    $cv = CheckVoucher::firstOrFail();
    $this->actingAs($this->user)->post(route('check-vouchers.mark-paid', $cv));

    expect($apv1->fresh()->status)->toBe('paid')->and($apv2->fresh()->status)->toBe('paid');

    $this->actingAs($this->user)->delete(route('check-vouchers.destroy', $cv));

    expect($apv1->fresh()->status)->toBe('unpaid')->and($apv2->fresh()->status)->toBe('unpaid');
});

it('still settles a cod purchase apv through the legacy single link', function () {
    $branch = \App\Models\Branch::create(['name' => 'COD Test Branch', 'address' => 'Test Address', 'is_active' => true]);
    $bank = \App\Models\BankAccount::create(['bank_name' => 'Test Bank', 'account_name' => 'Test Acct', 'account_number' => '000111222', 'is_active' => true, 'created_by' => $this->user->id]);

    $this->actingAs($this->user)->post(route('purchase-vouchers.store'), [
        'date' => now()->toDateString(),
        'branch_id' => $branch->id,
        'purchase_type' => 'cod',
        'vendor_id' => $this->vendorA->id,
        'buyer' => 'Tester',
        'bank_account_id' => $bank->id,
        'payment_method' => 'cash',
        'items' => [
            ['particulars' => 'COD item', 'cost_account_id' => $this->costAccount->id, 'amount_w_vat' => 112],
        ],
    ])->assertSessionHasNoErrors();

    $apv = PurchaseVoucher::firstOrFail();
    $cv = CheckVoucher::where('type', 'cod_purchase')->firstOrFail();

    expect($cv->purchase_voucher_id)->toBe($apv->id)
        ->and($cv->apvAllocations)->toHaveCount(0)
        ->and((float) $apv->fresh()->amount_paid)->toBe(112.0)
        ->and($apv->fresh()->status)->toBe('paid');
});

it('renders the create, index, show and apv pages for a multi-apv check voucher', function () {
    $apv1 = ($this->makeApv)('APV-R-1', $this->vendorA, 112);
    $apv2 = ($this->makeApv)('APV-R-2', $this->vendorB, 112);

    ($this->storeCv)([
        ['purchase_voucher_id' => $apv1->id, 'amount_w_vat' => 112],
        ['purchase_voucher_id' => $apv2->id, 'amount_w_vat' => 50],
    ]);
    $cv = CheckVoucher::firstOrFail();
    $this->actingAs($this->user)->post(route('check-vouchers.mark-paid', $cv));

    $this->actingAs($this->user)->get(route('check-vouchers.create', ['pay_apv' => $apv1->id]))
        ->assertOk()->assertSee('APV-R-1');
    $this->actingAs($this->user)->get(route('check-vouchers.index'))
        ->assertOk()->assertSee('APV-R-1')->assertSee('APV-R-2');
    $this->actingAs($this->user)->get(route('check-vouchers.show', $cv))
        ->assertOk()->assertSee('Purchase Vouchers Paid');
    $this->actingAs($this->user)->get(route('purchase-vouchers.show', $apv2))
        ->assertOk()->assertSee($cv->cv_no);
});
