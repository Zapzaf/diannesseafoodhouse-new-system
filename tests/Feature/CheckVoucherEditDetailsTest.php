<?php

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\CheckVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->branch = Branch::create(['name' => 'Edit Branch', 'address' => 'Addr', 'is_active' => true]);
    $this->costAccount = ChartOfAccount::create(['name' => 'Edit Details Cost Account', 'type' => 'debit_expense']);

    $this->actingAs($this->admin)->post(route('check-vouchers.store'), [
        'date' => '2026-08-14',
        'cv_no' => 'CV-E-0001',
        'type' => 'other_disbursement',
        'particulars' => 'PARTIAL PAYMENT',
        'payee_name' => 'DINAH ALONZO',
        'payment_method' => 'check',
        'branch_id' => $this->branch->id,
        'receipts' => [['cost_account_id' => $this->costAccount->id, 'amount_w_vat' => 112]],
    ]);

    $this->cv = CheckVoucher::firstOrFail();
});

it('lets an admin edit the voucher details and recalculates ewt', function () {
    $this->actingAs($this->admin)->put(route('check-vouchers.update', $this->cv), [
        'date' => '2026-08-20',
        'cv_no' => 'CV-E-0002',
        'payee_name' => 'New Payee',
        'particulars' => 'Full payment',
        'payment_method' => 'cash',
        'ewt_rate' => '0.01',
        'remarks' => 'Edited',
    ])->assertRedirect(route('check-vouchers.show', $this->cv));

    $cv = $this->cv->fresh();

    expect($cv->date->toDateString())->toBe('2026-08-20')
        ->and($cv->cv_no)->toBe('CV-E-0002')
        ->and($cv->payee_name)->toBe('New Payee')
        ->and($cv->particulars)->toBe('Full payment')
        ->and($cv->payment_method)->toBe('cash')
        ->and($cv->remarks)->toBe('Edited')
        ->and((float) $cv->ewt_amount)->toBe(1.0)
        ->and((float) $cv->amount_paid)->toBe(111.0)
        ->and($cv->updated_by)->toBe($this->admin->id);
});

it('forbids non-admins from editing the voucher details', function () {
    $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $this->branch->id]);

    $this->actingAs($manager)->put(route('check-vouchers.update', $this->cv), [
        'date' => '2026-08-20',
        'cv_no' => 'CV-E-0002',
        'payee_name' => 'New Payee',
        'particulars' => 'Full payment',
        'payment_method' => 'check',
    ])->assertForbidden();

    expect($this->cv->fresh()->payee_name)->toBe('DINAH ALONZO');
});
