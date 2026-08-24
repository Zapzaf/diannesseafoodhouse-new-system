<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Purchase & Disbursement Summary export — one detailed accounting-ledger
 * sheet per voucher type (APV, CV, PCV, Check Register), each with its own
 * column layout matching the client's own manual bookkeeping spreadsheet
 * (item/line-level rows, not just one row per voucher).
 */
class PurchaseDisbursementSummaryExport implements WithMultipleSheets
{
    /**
     * @param array<int, array{title: string, headings: array, rows: array, numericFormats: array, totals: array}> $sheetDefinitions
     */
    public function __construct(
        private readonly array $sheetDefinitions,
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    public function sheets(): array
    {
        return array_map(fn (array $def) => new LedgerSheet(
            $def['title'],
            $def['headings'],
            $def['rows'],
            $def['numericFormats'],
            $def['totals'],
            $this->dateFrom,
            $this->dateTo
        ), $this->sheetDefinitions);
    }
}
