<?php

namespace App\Exports;

use App\Models\MenuOrderPayment;
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

class MenuOrderSalesReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    /**
     * @param array<string, mixed> $summary
     */
    public function __construct(
        private readonly Collection $payments,
        private readonly array $summary
    ) {
    }

    public function collection(): Collection
    {
        $rows = $this->payments->map(fn (MenuOrderPayment $payment): array => [
            $payment->or_number ?? '',
            optional($payment->payment_date)->format('Y-m-d') ?? $payment->created_at?->format('Y-m-d') ?? '',
            $payment->branch?->name ?? '',
            $payment->order?->order_number ?? '',
            $payment->order?->customer_name ?: 'Walk-in',
            strtoupper((string) $payment->method),
            $payment->discount_type && $payment->discount_type !== 'none' ? ucfirst($payment->discount_type) : '',
            (float) ($payment->discount_amount ?? 0),
            $payment->promo_discount_label ?: ($payment->promo_discount_source ? ucfirst($payment->promo_discount_source) : ''),
            (float) ($payment->promo_discount_amount ?? 0),
            (float) $payment->subtotal + (float) ($payment->additional_charge_amount ?? 0),
            (float) ($payment->total_vat_exempt ?? 0),
            (float) ($payment->vat_amount ?? 0),
            (float) $payment->amount,
            $payment->receivedBy?->name ?? '',
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push(['', '', '', '', '', '', 'TOTAL', (float) $this->summary['total_discount'], '', (float) $this->summary['promo_discount_amount'], (float) $this->summary['gross_sales'], (float) $this->summary['vat_exempt_sales'], (float) $this->summary['vat_amount'], (float) $this->summary['amount_collected'], '']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'OR Number',
            'Date',
            'Branch',
            'Order Number',
            'Customer',
            'Method',
            'PWD/Senior Discount',
            'PWD/Senior Amount',
            'Promo Discount',
            'Promo Amount',
            'Gross',
            'VAT-Exempt',
            'VAT Amount',
            'Net Amount',
            'Received By',
        ];
    }

    public function title(): string
    {
        return 'Menu Order Sales Report';
    }

    public function columnFormats(): array
    {
        return [
            'H' => '"₱"#,##0.00',
            'J' => '"₱"#,##0.00',
            'K' => '"₱"#,##0.00',
            'L' => '"₱"#,##0.00',
            'M' => '"₱"#,##0.00',
            'N' => '"₱"#,##0.00',
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
                $sheet->getStyle('H:N')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
