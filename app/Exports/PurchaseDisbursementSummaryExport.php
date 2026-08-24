<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Purchase & Disbursement Summary export — one detailed, row-per-voucher
 * sheet per voucher type (not a single totals row), per the client's
 * request: APV, CV, PCV, and Check Register each get their own tab.
 */
class PurchaseDisbursementSummaryExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $apvRows,
        private readonly Collection $cvRows,
        private readonly Collection $pcvRows,
        private readonly Collection $checkRegisterRows,
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    public function sheets(): array
    {
        return [
            new DetailedVoucherSheet('APV', $this->apvRows, $this->dateFrom, $this->dateTo),
            new DetailedVoucherSheet('CV', $this->cvRows, $this->dateFrom, $this->dateTo),
            new DetailedVoucherSheet('PCV', $this->pcvRows, $this->dateFrom, $this->dateTo),
            new DetailedVoucherSheet('Check Register', $this->checkRegisterRows, $this->dateFrom, $this->dateTo),
        ];
    }
}
