<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpenseImport implements WithMultipleSheets
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function sheets(): array
    {
        return [
            0 => new VatablePurchasesSheet($this->monthYear, $this->branchId),
            1 => new NonVatableSheet($this->monthYear, $this->branchId),
            2 => new CashDisbursementSheet($this->monthYear, $this->branchId),
        ];
    }
}
