<?php

namespace App\Imports;

use App\Models\NonVatablePurchase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class NonVatableSheet implements ToModel, WithStartRow, WithChunkReading
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

        return new NonVatablePurchase([
            'branch_id'    => $this->branchId,
            'date'         => $date,
            'vendor_name'  => $vendorName,
            'gross_amount' => (float) ($row[5] ?? 0),
            'month_year'   => $this->monthYear,
        ]);
    }
}
