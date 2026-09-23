<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use App\Exceptions\DomainException;
use Modules\Ingestion\DTOs\ParsedFile;
use Modules\Ingestion\Support\CellValue;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use SplFileObject;

final class SpreadsheetReader
{
    public const DATA_SHEET = 'Data';

    public function read(string $path, string $extension, int $maxRows): ParsedFile
    {
        return strtolower($extension) === 'csv' ? $this->csv($path, $maxRows) : $this->xlsx($path, $maxRows);
    }

    private function xlsx(string $path, int $maxRows): ParsedFile
    {
        $reader = new XlsxReader(new Options(SHOULD_FORMAT_DATES: false, SHOULD_PRESERVE_EMPTY_ROWS: false));
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== self::DATA_SHEET) {
                    continue;
                }
                $headers = null;
                $rows = [];
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = array_map(fn ($cell) => CellValue::normalise($cell->getValue()), $row->cells);
                    if ($headers === null) {
                        $headers = $values;

                        continue;
                    }
                    $this->collect($rows, $headers, $values, $rowNumber, $maxRows);
                }

                return new ParsedFile($this->headerNames($headers ?? []), $rows);
            }
        } finally {
            $reader->close();
        }

        throw DomainException::invalid("The workbook has no '".self::DATA_SHEET."' sheet. Use the downloadable template.");
    }

    private function csv(string $path, int $maxRows): ParsedFile
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');
        $headers = null;
        $rows = [];
        $rowNumber = 0;
        foreach ($file as $line) {
            if (! is_array($line) || $line === [null]) {
                continue;
            }
            $rowNumber++;
            $values = array_map(fn ($v) => CellValue::normalise($v), $line);
            if ($headers === null) {
                $values[0] = isset($values[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', $values[0]) : null;
                $headers = $values;

                continue;
            }
            $this->collect($rows, $headers, $values, $rowNumber, $maxRows);
        }

        return new ParsedFile($this->headerNames($headers ?? []), $rows);
    }

    private function collect(array &$rows, array $headers, array $values, int $rowNumber, int $maxRows): void
    {
        if (array_filter($values, fn ($v) => $v !== null && $v !== '') === []) {
            return;
        }
        if (count($rows) >= $maxRows) {
            throw DomainException::invalid('The file has more than '.number_format($maxRows).' data rows.');
        }
        $record = [];
        foreach ($headers as $index => $header) {
            if ($header !== null && $header !== '') {
                $record[$header] = $values[$index] ?? null;
            }
        }
        $rows[$rowNumber] = $record;
    }

    private function headerNames(array $headers): array
    {
        $names = [];
        foreach ($headers as $header) {
            $names[] = $header === null ? '' : trim($header);
        }
        while ($names !== [] && end($names) === '') {
            array_pop($names);
        }

        return $names;
    }
}
