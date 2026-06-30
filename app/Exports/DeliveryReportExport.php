<?php

namespace App\Exports;

use App\Models\DeliveryItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeliveryReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    public function __construct(
        private readonly Collection $deliveryItems,
        private readonly float $totalDeliveryCost
    ) {
    }

    public function collection(): Collection
    {
        $rows = $this->deliveryItems->map(fn (DeliveryItem $deliveryItem): array => [
            $deliveryItem->delivery?->reference_number ?? '',
            $deliveryItem->delivery?->created_at?->format('Y-m-d H:i') ?? '',
            $deliveryItem->delivery?->destinationBranch?->name ?? '',
            $this->sourceName($deliveryItem),
            $this->itemName($deliveryItem),
            (float) $deliveryItem->quantity,
            $deliveryItem->unit,
            (float) ($deliveryItem->price ?? 0),
            strtoupper((string) ($deliveryItem->delivery?->status ?? '')),
            $deliveryItem->delivery?->approver?->name ?? '',
            $deliveryItem->delivery?->creator?->name ?? '',
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push(['', '', '', '', 'TOTAL DELIVERY COST', '', '', $this->totalDeliveryCost, '', '', '']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Delivery Reference',
            'Date',
            'Destination',
            'Source',
            'Item',
            'Quantity',
            'Unit',
            'Cost',
            'Status',
            'Approved By',
            'Created By',
        ];
    }

    public function title(): string
    {
        return 'Delivery Report';
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0.00',
            'H' => '"₱"#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        $styles = [
            1 => [
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
            "A1:{$lastColumn}{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ],
        ];

        if ($lastRow > 1) {
            $styles[$lastRow] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFEDF2F7'],
                ],
            ];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);

                $sheet->getPageMargins()
                    ->setTop(0.35)
                    ->setRight(0.25)
                    ->setBottom(0.35)
                    ->setLeft(0.25);

                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->getStyle('A:K')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('F:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    private function sourceName(DeliveryItem $deliveryItem): string
    {
        return $deliveryItem->delivery?->sourceBranch?->name
            ?? $deliveryItem->delivery?->supplier?->name
            ?? '';
    }

    private function itemName(DeliveryItem $deliveryItem): string
    {
        return $deliveryItem->description
            ?: ($deliveryItem->item?->name
                ?? $deliveryItem->sourceItem?->name
                ?? 'Unspecified item');
    }
}
