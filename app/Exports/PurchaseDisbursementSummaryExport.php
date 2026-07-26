<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PurchaseDisbursementSummaryExport implements WithMultipleSheets
{
    public function __construct(
        private readonly object $apvTotals,
        private readonly array $pcvTotals,
        private readonly object $cvTotals,
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    public function sheets(): array
    {
        return [
            new PurchaseDisbursementSummarySheet(
                'Purchase Vouchers (APV)',
                ['Net Purchases', 'VAT', 'VAT-Exempt', 'Non-VAT', 'Total Purchases'],
                [
                    (float) $this->apvTotals->net_purchases,
                    (float) $this->apvTotals->vat,
                    (float) $this->apvTotals->vat_exempt,
                    (float) $this->apvTotals->non_vat_purchase,
                    (float) $this->apvTotals->total_purchases,
                ],
                $this->dateFrom,
                $this->dateTo
            ),
            new PurchaseDisbursementSummarySheet(
                'Petty Cash Vouchers (PCV)',
                ['Net Purchases', 'VAT', 'VAT-Exempt', 'Non-VAT', 'Total Purchases'],
                [
                    (float) $this->pcvTotals['net_purchases'],
                    (float) $this->pcvTotals['vat'],
                    (float) $this->pcvTotals['vat_exempt'],
                    (float) $this->pcvTotals['non_vat_purchase'],
                    (float) $this->pcvTotals['total_purchases'],
                ],
                $this->dateFrom,
                $this->dateTo
            ),
            new PurchaseDisbursementSummarySheet(
                'Check Vouchers (CV)',
                ['Net Purchases', 'VAT', 'VAT-Exempt', 'Non-VAT', 'EWT Withheld', 'Amount Paid'],
                [
                    (float) $this->cvTotals->net_purchases,
                    (float) $this->cvTotals->vat,
                    (float) $this->cvTotals->vat_exempt,
                    (float) $this->cvTotals->non_vat_purchase,
                    (float) $this->cvTotals->ewt_amount,
                    (float) $this->cvTotals->amount_paid,
                ],
                $this->dateFrom,
                $this->dateTo
            ),
        ];
    }
}
