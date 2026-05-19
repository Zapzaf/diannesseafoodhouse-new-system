<?php

namespace App\Exports;

use App\Models\MenuOrderPayment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentsSalesExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function collection()
    {
        $query = MenuOrderPayment::query()
            ->selectRaw("
                payment_date,
                branch_id,
                SUM(CASE WHEN method='cash' THEN amount ELSE 0 END) as cash,
                SUM(CASE WHEN method='gcash' THEN amount ELSE 0 END) as gcash,
                SUM(CASE WHEN method='card' THEN amount ELSE 0 END) as card,
                SUM(CASE WHEN method='bank' THEN amount ELSE 0 END) as bank,
                SUM(final_total) as gross_sales,
                SUM(vat_amount) as vat_amount,
                SUM(total_vat_exempt) as vat_exempt,
                SUM(discount_amount) as discount,
                SUM(amount) as net_sales
            ")
            ->where('payment_date', 'like', $this->monthYear . '%');

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $records = $query->with('branch')
            ->groupBy('payment_date', 'branch_id')
            ->orderBy('payment_date', 'asc')
            ->get();

        $rows = collect();
        $totalCash = 0;
        $totalGcash = 0;
        $totalCard = 0;
        $totalBank = 0;
        $totalGross = 0;
        $totalVat = 0;
        $totalExempt = 0;
        $totalDiscount = 0;
        $totalNet = 0;

        foreach ($records as $r) {
            $rows->push([
                $r->payment_date?->format('Y-m-d') ?? '',
                $r->branch->name ?? 'All',
                (float) $r->cash,
                (float) $r->gcash,
                (float) $r->card,
                (float) $r->bank,
                (float) $r->gross_sales,
                (float) $r->vat_amount,
                (float) $r->vat_exempt,
                (float) $r->discount,
                (float) $r->net_sales,
            ]);
            $totalCash += (float) $r->cash;
            $totalGcash += (float) $r->gcash;
            $totalCard += (float) $r->card;
            $totalBank += (float) $r->bank;
            $totalGross += (float) $r->gross_sales;
            $totalVat += (float) $r->vat_amount;
            $totalExempt += (float) $r->vat_exempt;
            $totalDiscount += (float) $r->discount;
            $totalNet += (float) $r->net_sales;
        }

        if ($rows->count() > 0) {
            $rows->push([
                'TOTAL',
                '',
                $totalCash,
                $totalGcash,
                $totalCard,
                $totalBank,
                $totalGross,
                $totalVat,
                $totalExempt,
                $totalDiscount,
                $totalNet,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Date', 'Branch', 'Cash', 'GCash', 'Card', 'Bank', 'Gross Sales',
            'VAT Amount', 'VAT Exempt', 'Discount', 'Net Sales'
        ];
    }

    public function title(): string
    {
        return 'DAILY SALES';
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
            'C2:K' . $lastRow => [
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
