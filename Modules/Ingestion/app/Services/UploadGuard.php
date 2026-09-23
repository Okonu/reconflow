<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use App\Exceptions\DomainException;
use Illuminate\Http\UploadedFile;
use ZipArchive;

final class UploadGuard
{
    private const XLSX_MIMES = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'];

    private const CSV_MIMES = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv'];

    public function extensionOf(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw DomainException::invalid('Only .xlsx and .csv files are accepted. Macro-enabled (.xlsm) and other formats are rejected.');
        }
        $mime = (string) $file->getMimeType();

        if ($extension === 'csv') {
            if (! in_array($mime, self::CSV_MIMES, true)) {
                throw DomainException::invalid('The file content does not match its .csv extension.');
            }

            return $extension;
        }

        if (! in_array($mime, self::XLSX_MIMES, true) || ! $this->isPlainWorkbook($file->getRealPath())) {
            throw DomainException::invalid('The file content does not match its .xlsx extension, or the workbook contains macros.');
        }

        return $extension;
    }

    private function isPlainWorkbook(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            if ($zip->locateName('xl/workbook.xml') === false || $zip->locateName('xl/vbaProject.bin') !== false) {
                return false;
            }
            $types = (string) $zip->getFromName('[Content_Types].xml');

            return ! str_contains($types, 'macroEnabled');
        } finally {
            $zip->close();
        }
    }
}
