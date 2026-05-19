<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpenseReportExport implements WithMultipleSheets
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function sheets(): array
    {
        return [
            new PaymentsSalesExport($this->monthYear, $this->branchId),
            new VatablePurchasesExport($this->monthYear, $this->branchId),
            new NonVatableExport($this->monthYear, $this->branchId),
            new CashDisbursementExport($this->monthYear, $this->branchId),
        ];
    }
}
