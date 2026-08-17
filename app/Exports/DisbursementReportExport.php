<?php

namespace App\Exports;

use App\Models\CheckVoucher;
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

class DisbursementReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    public function __construct(private readonly Collection $checkVouchers)
    {
    }

    public function collection(): Collection
    {
        return $this->checkVouchers->map(fn (CheckVoucher $cv): array => [
            $cv->cv_no ?? '',
            $cv->reference_no ?? '',
            $cv->date?->format('Y-m-d') ?? '',
            ucwords(str_replace('_', ' ', $cv->type)),
            $cv->branch?->name ?? '',
            $cv->payee_name ?? '',
            $cv->supplier?->name ?? '',
            $cv->particulars ?? '',
            ucwords(str_replace('_', ' ', $cv->payment_method)),
            (float) $cv->net_purchases,
            (float) $cv->vat,
            (float) $cv->vat_exempt,
            (float) $cv->non_vat_purchase,
            (float) $cv->amount_w_vat,
            (float) $cv->ewt_amount,
            (float) $cv->amount_paid,
            ucfirst($cv->status),
            $cv->creator?->name ?? '',
            $cv->creator?->email ?? '',
            $cv->updater?->name ?? '',
        ]);
    }

    public function headings(): array
    {
        return [
            'CV #',
            'Reference #',
            'Date',
            'Type',
            'Branch',
            'Payee',
            'Supplier',
            'Particulars',
            'Payment Method',
            'Net Purchases',
            'VAT',
            'VAT-Exempt',
            'Non-VAT',
            'Amount w/ VAT',
            'EWT Withheld',
            'Amount Paid',
            'Status',
            'Created By',
            'Created By (Email)',
            'Last Updated By',
        ];
    }

    public function title(): string
    {
        return 'Disbursements';
    }

    public function columnFormats(): array
    {
        return [
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
            'M' => '#,##0.00',
            'N' => '#,##0.00',
            'O' => '#,##0.00',
            'P' => '#,##0.00',
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
                $sheet->getStyle('A:T')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('J:P')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
