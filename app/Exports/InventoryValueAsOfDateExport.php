<?php

namespace App\Exports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Inventory Value As-Of-Date export — mirrors reports/inventory.blade.php:
 * Sheet 1 is every item's quantity/cost/value as of the chosen date, Sheet 2
 * is today's live Low Stock Items alert.
 */
class InventoryValueAsOfDateExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $snapshotItems,
        private readonly Collection $lowStockItems,
        private readonly string $asOfDate
    ) {
    }

    public function sheets(): array
    {
        return [
            new InventoryAsOfDateSheet($this->snapshotItems, $this->asOfDate),
            new InventoryLowStockSheet($this->lowStockItems),
        ];
    }
}

class InventoryAsOfDateSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        private readonly Collection $items,
        private readonly string $asOfDate
    ) {
    }

    public function collection(): Collection
    {
        return $this->items->values()->map(fn (Item $item, int $index): array => [
            $index + 1,
            $item->name,
            $item->branch?->name ?? '',
            $item->category?->location?->name ?? '',
            $item->category?->name ?? '',
            $item->unit ?? '',
            (float) $item->quantity_as_of,
            (float) $item->low_stock_threshold,
            (float) $item->cost_as_of,
            (float) $item->value_as_of,
            $this->status($item),
        ]);
    }

    public function headings(): array
    {
        return [
            '#', 'Item Name', 'Branch', 'Location', 'Category', 'Unit',
            'Quantity', 'Threshold', 'Unit Cost', 'Total Value', 'Status',
        ];
    }

    public function title(): string
    {
        return 'All Items as of '.\Illuminate\Support\Carbon::parse($this->asOfDate)->format('Y-m-d');
    }

    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '"₱"#,##0.00',
            'J' => '"₱"#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D3748']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    private function status(Item $item): string
    {
        if ((float) $item->quantity_as_of <= 0) {
            return 'OUT OF STOCK';
        }

        return (float) $item->quantity_as_of <= (float) $item->low_stock_threshold ? 'LOW STOCK' : 'OK';
    }
}

class InventoryLowStockSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(private readonly Collection $items)
    {
    }

    public function collection(): Collection
    {
        return $this->items->values()->map(fn (Item $item): array => [
            $item->name,
            $item->branch?->name ?? '',
            $item->category?->location?->name ?? '',
            $item->category?->name ?? '',
            (float) $item->quantity,
            (float) $item->low_stock_threshold,
            (float) ($item->unit_price ?? 0),
        ]);
    }

    public function headings(): array
    {
        return ['Item', 'Branch', 'Location', 'Category', 'Current Stock', 'Threshold', 'Unit Cost'];
    }

    public function title(): string
    {
        return 'Low Stock Items';
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '"₱"#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFB45309']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }
}
