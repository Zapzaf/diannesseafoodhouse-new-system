<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseDisbursementSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $headings,
        private readonly array $totalsRow,
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    public function array(): array
    {
        return [$this->totalsRow];
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $sheet->getHighestDataColumn();

        $sheet->insertNewRowBefore(1, 1);
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', "{$this->sheetTitle} — {$this->dateFrom} to {$this->dateTo}");

        $currencyFormat = '"₱"#,##0.00';
        foreach (range(1, count($this->headings)) as $colIndex) {
            $sheet->getStyleByColumnAndRow($colIndex, 3)->getNumberFormat()->setFormatCode($currencyFormat);
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 13],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ],
            2 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2D3748'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            3 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFEDF2F7'],
                ],
            ],
            "A2:{$lastColumn}3" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ],
        ];
    }
}
