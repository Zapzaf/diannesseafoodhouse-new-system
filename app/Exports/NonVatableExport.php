<?php

namespace App\Exports;

use App\Models\NonVatablePurchase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class NonVatableExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function collection()
    {
        $query = NonVatablePurchase::where('month_year', $this->monthYear);
        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $records = $query->with('branch')->get();
        $rows = collect();
        $totalGross = 0;

        foreach ($records as $r) {
            $rows->push([
                $r->date?->format('Y-m-d') ?? '',
                $r->branch->name ?? 'All',
                $r->vendor_name,
                (float) $r->gross_amount,
            ]);
            $totalGross += (float) $r->gross_amount;
        }

        if ($rows->count() > 0) {
            $rows->push(['TOTAL', '', '', $totalGross]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Date', 'Branch', 'Vendor Name', 'Gross Amount'];
    }

    public function title(): string
    {
        return 'NON-VATABLE';
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
            'D2:D' . $lastRow => [
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
