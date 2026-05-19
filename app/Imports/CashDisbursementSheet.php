<?php

namespace App\Imports;

use App\Models\CashDisbursement;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class CashDisbursementSheet implements ToModel, WithStartRow, WithChunkReading
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
        $payee = trim((string) ($row[2] ?? ''));
        if (empty($payee)) {
            return null;
        }

        $dateValue = $row[0] ?? null;
        $date = null;
        if (is_numeric($dateValue)) {
            $date = Date::excelToDateTimeObject($dateValue);
        } elseif ($dateValue) {
            $date = Carbon::parse($dateValue);
        }

        return new CashDisbursement([
            'branch_id'    => $this->branchId,
            'date'         => $date,
            'check_number' => trim((string) ($row[1] ?? '')),
            'payee'        => $payee,
            'amount'       => (float) ($row[3] ?? 0),
            'reference'    => trim((string) ($row[4] ?? '')),
            'month_year'   => $this->monthYear,
        ]);
    }
}
