<?php

namespace App\Exports;

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

/**
 * One tab of the Purchase & Disbursement Summary export — the detailed,
 * row-per-voucher listing for a single voucher type (APV / CV / PCV /
 * Check Register), rather than a single aggregated totals row.
 *
 * @property-read \Illuminate\Support\Collection<int, object{voucher_no:string,date:\Illuminate\Support\Carbon,payee:string,tin:?string,particulars:string,branch:?string,status:?string,amount:float}> $rows
 */
class DetailedVoucherSheet implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly Collection $rows,
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    public function collection(): Collection
    {
        $rows = $this->rows->map(fn (object $row): array => [
            $row->voucher_no ?? '—',
            $row->date?->format('Y-m-d') ?? '',
            $row->payee ?? '—',
            $row->tin ?? '—',
            $row->particulars ?? '—',
            $row->branch ?? '—',
            $row->status ? strtoupper((string) $row->status) : '—',
            (float) $row->amount,
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push(['', '', '', '', '', '', 'TOTAL', (float) $this->rows->sum('amount')]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Voucher #',
            'Date',
            'Supplier / Payee',
            'TIN',
            'Particulars',
            'Branch',
            'Status',
            'Amount',
        ];
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    public function columnFormats(): array
    {
        return [
            'H' => '"₱"#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

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

        if ($lastRow > 1 && $this->rows->isNotEmpty()) {
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
                $sheet->getStyle('A:H')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('H:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($sheet->getHighestDataRow() <= 1) {
                    $sheet->setCellValue('A2', 'No records for this period.');
                    $sheet->mergeCells('A2:H2');
                    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A2')->getFont()->setItalic(true);
                }
            },
        ];
    }
}
