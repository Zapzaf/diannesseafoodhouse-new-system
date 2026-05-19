<?php

namespace App\Imports;

use App\Models\VatablePurchase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class VatablePurchasesSheet implements ToModel, WithStartRow, WithChunkReading
{
    public function __construct(public string $monthYear, public ?int $branchId)
    {
    }

    public function startRow(): int
    {
        return 5;
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function model(array $row)
    {
        $vendorName = trim((string) ($row[1] ?? ''));
        if (empty($vendorName)) {
            return null;
        }

        $dateValue = $row[0] ?? null;
        $date = null;
        if (is_numeric($dateValue)) {
            $date = Date::excelToDateTimeObject($dateValue);
        } elseif ($dateValue) {
            $date = Carbon::parse($dateValue);
        }

        return new VatablePurchase([
            'branch_id'     => $this->branchId,
            'date'          => $date,
            'vendor_name'   => $vendorName,
            'address'       => trim((string) ($row[2] ?? '')),
            'si_number'     => trim((string) ($row[3] ?? '')),
            'tin'           => trim((string) ($row[4] ?? '')),
            'gross_amount'  => (float) ($row[5] ?? 0),
            'vat'           => (float) ($row[6] ?? 0),
            'net_purchases' => (float) ($row[7] ?? 0),
            'month_year'    => $this->monthYear,
        ]);
    }
}
