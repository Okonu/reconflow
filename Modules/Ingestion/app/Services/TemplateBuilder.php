<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use App\Support\Export\SpreadsheetSafe;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Support\Schema\Column;
use Modules\Ingestion\Support\Schema\ColumnType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class TemplateBuilder
{
    public const HEADER_FILL = '2D7F67';

    public const WARNING_COLOUR = 'B03A2E';

    private const VALIDATION_ROWS = 50001;

    public function build(SourceType $source, array $rows = []): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator('ReconFlow')->setTitle('ReconFlow upload template: '.$source->schema()->title());

        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Data');
        $this->dataSheet($data, $source, $rows);
        $this->instructionsSheet($spreadsheet->createSheet(), $source);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function dataSheet(Worksheet $sheet, SourceType $source, array $rows): void
    {
        $columns = $source->schema()->columns();
        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$letter}1", $column->name);
            $sheet->getColumnDimension($letter)->setWidth($column->width);
            $sheet->getStyle("{$letter}:{$letter}")->getNumberFormat()->setFormatCode($column->type->excelNumberFormat());
            $this->validation($sheet, $column, $letter);
        }
        $last = Coordinate::stringFromColumnIndex(count($columns));
        $this->headerStyle($sheet, "A1:{$last}1");
        $sheet->freezePane('A2');

        foreach (array_values($rows) as $r => $row) {
            foreach ($columns as $index => $column) {
                $value = SpreadsheetSafe::escape($row[$column->name] ?? null);
                if ($value === null) {
                    continue;
                }
                $cell = Coordinate::stringFromColumnIndex($index + 1).($r + 2);
                $numeric = $column->type === ColumnType::Money && is_numeric($value);
                $sheet->setCellValueExplicit($cell, $numeric ? (float) $value : (string) $value, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
    }

    private function validation(Worksheet $sheet, Column $column, string $letter): void
    {
        $rule = match ($column->type) {
            ColumnType::Choice => [DataValidation::TYPE_LIST, null, '"'.implode(',', $column->choices).'"', 'Invalid value', 'Choose a value from the list.'],
            ColumnType::Phone => [DataValidation::TYPE_TEXTLENGTH, DataValidation::OPERATOR_EQUAL, '12', 'Invalid phone', 'Use 12 digits, e.g. 254700123456.'],
            ColumnType::Money => [DataValidation::TYPE_DECIMAL, DataValidation::OPERATOR_GREATERTHAN, '0', 'Invalid amount', 'Amount must be a number greater than 0.'],
            default => null,
        };
        if ($rule === null) {
            return;
        }
        [$type, $operator, $formula, $title, $message] = $rule;
        $validation = new DataValidation;
        $validation->setType($type);
        if ($operator !== null) {
            $validation->setOperator($operator);
        }
        $validation->setFormula1($formula);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown($type !== DataValidation::TYPE_LIST);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setErrorTitle($title);
        $validation->setError($message);
        $sheet->setDataValidation("{$letter}2:{$letter}".self::VALIDATION_ROWS, $validation);
    }

    private function instructionsSheet(Worksheet $sheet, SourceType $source): void
    {
        $schema = $source->schema();
        $sheet->setTitle('Instructions');
        $lines = [
            ['ReconFlow upload template: '.$schema->title()],
            [],
            ['How to use'],
            ["1. Enter one record per row on the 'Data' sheet, starting at row 2. Do not rename, reorder or delete the header row."],
            ["2. Only the 'Data' sheet is read. This 'Instructions' sheet is ignored on upload."],
            ['3. Save as .xlsx (or export the Data sheet as .csv, UTF-8). Maximum 50,000 rows or 10 MB per file. Macro-enabled files are rejected.'],
            ['4. In ReconFlow: Data uploads → choose source and business date → upload → review the preview → Confirm import.'],
            ['5. Invalid rows are shown in the preview with the reason and are quarantined, not silently dropped.'],
            ['SYNTHETIC DATA ONLY: never put real customer data in test files.'],
            [],
            ['Column', 'Required', 'Type / format', 'Rules', 'Example'],
        ];
        foreach ($schema->columns() as $column) {
            $lines[] = [$column->name, $column->requirement->value, $column->formatLabel(), $column->rules, $column->example];
        }
        $lines[] = [];
        $lines[] = ['Example row (format reference; do not copy into the Data sheet)'];
        $lines[] = $schema->headers();
        $lines[] = array_map(fn (Column $c): string => $c->example, $schema->columns());

        foreach ($lines as $r => $values) {
            foreach ($values as $c => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($r + 1), $value, DataType::TYPE_STRING);
            }
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->getStyle('A9')->getFont()->setBold(true)->getColor()->setRGB(self::WARNING_COLOUR);
        $this->headerStyle($sheet, 'A11:E11');
        $exampleHeader = count($lines) - 1;
        $sheet->getStyle('A'.$exampleHeader.':'.Coordinate::stringFromColumnIndex(count($schema->columns())).$exampleHeader)->getFont()->setBold(true);
        foreach (['A' => 20, 'B' => 11, 'C' => 30, 'D' => 60, 'E' => 22] as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
    }

    private function headerStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setName('Arial')->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
    }
}
