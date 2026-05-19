<?php

namespace App\Exports;

use App\Models\VatablePurchase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VatablePurchasesExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function collection()
    {
        $query = VatablePurchase::where('month_year', $this->monthYear);
        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $records = $query->with('branch')->get();
        $rows = collect();

        $totalGross = 0;
        $totalVat = 0;
        $totalNet = 0;

        foreach ($records as $r) {
            $rows->push([
                $r->date?->format('Y-m-d') ?? '',
                $r->branch->name ?? 'All',
                $r->vendor_name,
                $r->address,
                $r->si_number,
                $r->tin,
                (float) $r->gross_amount,
                (float) $r->vat,
                (float) $r->net_purchases,
            ]);
            $totalGross += (float) $r->gross_amount;
            $totalVat += (float) $r->vat;
            $totalNet += (float) $r->net_purchases;
        }

        if ($rows->count() > 0) {
            $rows->push([
                'TOTAL', '', '', '', '', '',
                $totalGross, $totalVat, $totalNet
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Date', 'Branch', 'Vendor Name', 'Address', 'SI No.', 'TIN',
            'Gross Amount', 'VAT', 'Net Purchases'
        ];
    }

    public function title(): string
    {
        return 'VATABLE';
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestDataRow();
        $sheet->freezePane('A2');

        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2D3748']
                ],
            ],
            'G2:I' . $lastRow => [
                'numberFormat' => ['formatCode' => '#,##0.00'],
            ],
        ];

        if ($lastRow > 1) {
            $styles[$lastRow] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFEDF2F7']
                ],
            ];
        }

        return $styles;
    }
}
