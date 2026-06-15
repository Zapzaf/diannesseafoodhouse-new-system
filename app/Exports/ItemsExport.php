<?php

namespace App\Exports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ItemsExport implements WithMultipleSheets
{
    public function __construct(private readonly int $branchId)
    {
    }

    public function sheets(): array
    {
        $sheets = [];
        $items = Item::query()
            ->with(['branch', 'category.location'])
            ->where('branch_id', $this->branchId)
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $groupedByLocation = $items->groupBy(
            fn (Item $item) => $item->category?->location?->name ?? 'No Location'
        );

        if ($groupedByLocation->isEmpty()) {
            $groupedByLocation = collect([
                'Inventory' => collect(),
            ]);
        }

        foreach ($groupedByLocation as $locationName => $locationItems) {
            $sheets[] = new ItemsCategorySheet($locationName, $locationItems);
        }

        return $sheets;
    }
}

class ItemsCategorySheet implements FromArray, WithStyles
{
    public function __construct(
        private readonly string $sheetName,
        private readonly Collection $items
    ) {
    }

    public function array(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setTitle(substr($this->sheetName, 0, 31));

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(22);
        $sheet->getColumnDimension('K')->setWidth(14);
        $sheet->getColumnDimension('L')->setWidth(14);
        $sheet->getColumnDimension('M')->setWidth(28);

        $rowIndex = 1;

        $groupedItems = $this->items->groupBy(fn (Item $item): string => $item->category?->name ?? 'Uncategorized');

        if ($groupedItems->isEmpty()) {
            $groupedItems = collect([
                'Inventory Items' => collect(),
            ]);
        }

        foreach ($groupedItems as $groupName => $groupItems) {
            $sheet->mergeCells('A' . $rowIndex . ':M' . $rowIndex);
            $sheet->setCellValue('A' . $rowIndex, $groupName);

            $subcategoryHeaderStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 18,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E7E6E6'],
                ],
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THICK,
                    ],
                ],
            ];

            $sheet->getStyle('A' . $rowIndex . ':M' . $rowIndex)->applyFromArray($subcategoryHeaderStyle);
            $rowIndex++;

            $columnHeaders = [
                'Item ID',
                'SKU',
                'Name',
                'Branch',
                'Location',
                'Category',
                'Unit',
                'Remaining Qty',
                'Low Stock Threshold',
                'Supplier',
                'Status',
                'Actual',
                'Remarks',
            ];

            $headerStyle = [
                'font' => [
                    'bold' => true,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THICK,
                    ],
                ],
            ];

            for ($col = 1; $col <= 13; $col++) {
                $colLetter = $this->getColumnLetter($col);
                $sheet->setCellValue($colLetter . $rowIndex, $columnHeaders[$col - 1]);
                $sheet->getStyle($colLetter . $rowIndex)->applyFromArray($headerStyle);
            }
            $rowIndex++;

            foreach ($groupItems as $item) {
                $quantity = (float) $item->quantity;
                $threshold = (float) $item->low_stock_threshold;
                $status = $quantity <= 0
                    ? 'OUT OF STOCK'
                    : ($quantity <= $threshold ? 'LOW STOCK' : 'OK');

                $dataRow = [
                    $item->id,
                    $item->sku ?? '',
                    $item->name,
                    $item->branch?->name ?? '',
                    $item->category?->location?->name ?? '',
                    $item->category?->name ?? '',
                    $item->unit ?? '',
                    $quantity,
                    $threshold,
                    $item->supplier_name ?? '',
                    $status,
                    '',
                    $item->notes ?? '',
                ];

                for ($col = 1; $col <= 13; $col++) {
                    $colLetter = $this->getColumnLetter($col);
                    $sheet->setCellValue($colLetter . $rowIndex, $dataRow[$col - 1]);
                }

                $dataStyle = [
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];

                for ($col = 1; $col <= 13; $col++) {
                    $colLetter = $this->getColumnLetter($col);
                    $sheet->getStyle($colLetter . $rowIndex)->applyFromArray($dataStyle);
                }

                $sheet->getStyle('H' . $rowIndex . ':I' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0.00');

                if ($quantity <= 0) {
                    $sheet->getStyle('A' . $rowIndex . ':M' . $rowIndex)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEE2E2'],
                        ],
                    ]);
                } elseif ($quantity <= $threshold) {
                    $sheet->getStyle('H' . $rowIndex . ':K' . $rowIndex)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEF3C7'],
                        ],
                    ]);
                }

                $rowIndex++;
            }

            $rowIndex++;
        }

        return [];
    }

    private function getColumnLetter(int $col): string
    {
        $letters = range('A', 'Z');

        return $letters[$col - 1] ?? 'A';
    }
}
