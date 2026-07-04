<?php

namespace App\Observers;

use App\Models\CheckRegister;

class CheckRegisterObserver
{
    public function created(CheckRegister $checkRegister): void
    {
        $checkVoucher = $checkRegister->checkVoucher;
        $checkVoucher->update(['status' => 'issued']);

        if ($checkVoucher->purchase_voucher_id) {
            $checkVoucher->purchaseVoucher->recomputeStatus();
        }
    }

    public function updated(CheckRegister $checkRegister): void
    {
        if (! $checkRegister->wasChanged('status')) {
            return;
        }

        $checkVoucher = $checkRegister->checkVoucher;

        if (in_array($checkRegister->status, ['cleared', 'voided'], true)) {
            $checkVoucher->update(['status' => $checkRegister->status]);
        }

        if ($checkVoucher->purchase_voucher_id) {
            $checkVoucher->purchaseVoucher->recomputeStatus();
        }
    }
}
