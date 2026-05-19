<?php

namespace App\Exports;

use App\Models\CashDisbursement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CashDisbursementExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function collection()
    {
        $query = CashDisbursement::where('month_year', $this->monthYear);
        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $records = $query->with('branch')->get();
        $rows = collect();
        $totalAmount = 0;

        foreach ($records as $r) {
            $rows->push([
                $r->date?->format('Y-m-d') ?? '',
                $r->branch->name ?? 'All',
                $r->check_number,
                $r->payee,
                (float) $r->amount,
                $r->reference,
            ]);
            $totalAmount += (float) $r->amount;
        }

        if ($rows->count() > 0) {
            $rows->push(['TOTAL', '', '', '', $totalAmount, '']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Date', 'Branch', 'Check No.', 'Payee', 'Amount', 'Reference'];
    }

    public function title(): string
    {
        return 'CASH DISBURSEMENT';
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
            'E2:E' . $lastRow => [
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
