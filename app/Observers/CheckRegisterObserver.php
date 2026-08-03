<?php

namespace App\Observers;

use App\Models\CheckRegister;

class CheckRegisterObserver
{
    public function created(CheckRegister $checkRegister): void
    {
        $checkVoucher = $checkRegister->checkVoucher;
        $checkVoucher->update(['status' => 'issued']);

        $this->recomputeParent($checkVoucher);
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

        $this->recomputeParent($checkVoucher);
    }

    private function recomputeParent(\App\Models\CheckVoucher $checkVoucher): void
    {
        if ($checkVoucher->purchase_voucher_id) {
            $checkVoucher->purchaseVoucher->recomputeStatus();
        }

        if ($checkVoucher->service_id) {
            $checkVoucher->service->recomputeStatus();
        }
    }
}
