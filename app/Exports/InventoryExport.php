<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InventoryExport implements FromArray, WithStyles
{
    private array $data;
    private string $title;
    private float $totalInventoryCostRaw;

    public function __construct(array $data, string $title, float $totalInventoryCostRaw)
    {
        $this->data = $data;
        $this->title = $title;
        $this->totalInventoryCostRaw = $totalInventoryCostRaw;
    }

    public function array(): array
    {
        $rows = [
            [$this->title],
            [],
            [
                'Item ID',
                'Item Name',
                'Category',
                'Subcategory',
                'Unit',
                'Latest Activity ID',
                'Last Activity Date',
                'Unit Price',
                'Remaining Qty',
                'Total Price',
            ],
        ];

        foreach ($this->data as $item) {
            $rows[] = [
                $item['item_id'] ?? '',
                $item['item_name'] ?? '',
                $item['category'] ?? '',
                $item['subcategory'] ?? '',
                $item['unit'] ?? '',
                $item['last_activity_id'] ?? '',
                $item['last_activity_date'] ?? '',
                (float) ($item['item_price_raw'] ?? 0),
                (float) ($item['remaining_qty_raw'] ?? 0),
                (float) ($item['total_price_raw'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Total Items', '', '', '', '', '', '', '', '', count($this->data)];
        $rows[] = ['Total Inventory Cost', '', '', '', '', '', '', '', '', $this->totalInventoryCostRaw];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $dataStartRow = 4;
        $dataEndRow = 3 + count($this->data);
        $totalItemsRow = 5 + count($this->data);
        $grandTotalRow = 6 + count($this->data);
        $currencyFormat = '"₱"#,##0.00';

        $sheet->setTitle('Inventory Snapshot');
        $sheet->mergeCells('A1:J1');
        $sheet->freezePane('A4');
        $sheet->setAutoFilter('A3:J3');

        foreach ([
            'A' => 18,
            'B' => 28,
            'C' => 18,
            'D' => 20,
            'E' => 12,
            'F' => 22,
            'G' => 24,
            'H' => 14,
            'I' => 16,
            'J' => 16,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $titleStyle = [
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'ED7D31'],
            ],
        ];

        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'ED7D31'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $dataStyle = [
            'font' => [
                'size' => 11,
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $totalStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FCE4D6'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $sheet->getStyle('A1:J1')->applyFromArray($titleStyle);
        $sheet->getStyle('A3:J3')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(3)->setRowHeight(22);

        if (count($this->data) > 0) {
            $sheet->getStyle("A{$dataStartRow}:J{$dataEndRow}")->applyFromArray($dataStyle);
            $sheet->getStyle("H{$dataStartRow}:J{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$dataStartRow}:H{$dataEndRow}")->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle("I{$dataStartRow}:I{$dataEndRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
            $sheet->getStyle("J{$dataStartRow}:J{$dataEndRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        }

        $sheet->mergeCells("A{$totalItemsRow}:I{$totalItemsRow}");
        $sheet->mergeCells("A{$grandTotalRow}:I{$grandTotalRow}");
        $sheet->getStyle("A{$totalItemsRow}:J{$grandTotalRow}")->applyFromArray($totalStyle);
        $sheet->getStyle("J{$totalItemsRow}:J{$grandTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("J{$grandTotalRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle("J{$grandTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
