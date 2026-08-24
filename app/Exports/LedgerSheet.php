<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One tab of a detailed accounting ledger export (Purchase & Disbursement
 * Summary) — built generically so APV, CV, PCV, and Check Register can each
 * have their own real column layout (matching the client's own manual
 * bookkeeping spreadsheet) instead of forcing all four into one shared
 * shape. Every row here is already a plain, fully-ordered array matching
 * $headings — all the domain-specific "what does a row look like for this
 * voucher type" logic lives in the controller, not in this class.
 */
class LedgerSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents, WithTitle, WithCustomValueBinder
{
    /**
     * @param string $sheetTitle Excel tab name (max 31 chars, truncated).
     * @param array<int, string> $headings Column headers, left-to-right.
     * @param array<int, array<int, mixed>> $rows Row data, one array per row, same order as $headings.
     * @param array<string, string> $numericFormats Column letter => number format code, for every column that should stay a real number (everything else is forced to Text — long digit strings like TIN/SI# otherwise get mangled into scientific notation by Excel).
     * @param array<string, string>|null $totals Column letter => SUM formula target, rendered as a bold totals row under the data (e.g. ['F' => 'Amount']). Null/empty skips the totals row.
     */
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $numericFormats,
        private readonly array $totals,
        private readonly string $dateFrom,
        private readonly string $dateTo
    ) {
    }

    /**
     * Every column is Text except the ones explicitly marked numeric —
     * forces the actual cell type, not just its display format, since a
     * column-level "@" format alone doesn't reliably stop Excel from having
     * already auto-detected a long digit string (TIN, SI#, etc.) as a real
     * number and rendering it in scientific notation regardless.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (array_key_exists($cell->getColumn(), $this->numericFormats) && is_numeric($value)) {
            return (new DefaultValueBinder())->bindValue($cell, $value);
        }

        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function array(): array
    {
        $rows = $this->rows;

        if (! empty($this->totals) && count($rows) > 0) {
            $totalsRow = array_fill(0, count($this->headings), '');
            $totalsRow[0] = 'TOTAL';

            foreach (array_keys($this->totals) as $column) {
                $index = $this->columnIndex($column);
                $sum = array_sum(array_map(fn (array $row) => is_numeric($row[$index] ?? null) ? (float) $row[$index] : 0.0, $rows));
                $totalsRow[$index] = $sum;
            }

            $rows[] = $totalsRow;
        }

        return $rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    public function columnFormats(): array
    {
        return $this->numericFormats;
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

        if ($lastRow > 1 && ! empty($this->totals) && count($this->rows) > 0) {
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
                $lastColumn = $sheet->getHighestDataColumn();

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
                $sheet->getStyle("A:{$lastColumn}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                foreach (array_keys($this->numericFormats) as $column) {
                    $sheet->getStyle("{$column}:{$column}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                if ($sheet->getHighestDataRow() <= 1) {
                    $sheet->setCellValue('A2', 'No records for this period.');
                    $sheet->mergeCells("A2:{$lastColumn}2");
                    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A2')->getFont()->setItalic(true);
                }
            },
        ];
    }

    private function columnIndex(string $column): int
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) - 1;
    }
}
