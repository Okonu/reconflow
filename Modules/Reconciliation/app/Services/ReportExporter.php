<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use App\Support\Export\SpreadsheetSafe;
use Illuminate\Database\Query\Builder;
use Modules\DataProtection\Support\PersonalData;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use stdClass;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExporter
{
    public const HEADERS = [
        'Transaction ID', 'Business date', 'Section', 'Expected amount', 'Actual amount', 'Posted amount', 'Variance', 'Status', 'Detailed status',
        'Rule', 'Tag', 'Payment IDs', 'Customer phone', 'Payer phone(s)', 'Region', 'Agent',
    ];

    public function csv(Builder $rows, string $filename, bool $masked): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $masked): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, self::HEADERS, escape: '');
            foreach ($rows->cursor() as $row) {
                fputcsv($out, $this->line($row, $masked), escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(Builder $rows, string $filename, bool $masked): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'recon-report-');
        $writer = new Writer;
        $writer->openToFile((string) $path);
        $writer->addRow(Row::fromValues(self::HEADERS));
        foreach ($rows->cursor() as $row) {
            $line = $this->line($row, $masked);
            foreach ([3, 4, 5, 6] as $i) {
                $line[$i] = $line[$i] === '' ? null : (float) $line[$i];
            }
            $writer->addRow(Row::fromValues($line));
        }
        $writer->close();

        return response()->download((string) $path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function line(stdClass $row, bool $masked): array
    {
        $status = (string) ($row->effective_status ?? $row->status);
        $phones = $row->payer_phones === null ? '' : (string) $row->payer_phones;

        return SpreadsheetSafe::row([
            $row->transaction_id ?? '',
            (string) $row->business_date,
            (string) $row->section,
            $row->expected_amount === null ? '' : (string) $row->expected_amount,
            $row->actual_amount === null ? '' : (string) $row->actual_amount,
            $row->posted_amount === null ? '' : (string) $row->posted_amount,
            $row->variance === null ? '' : (string) $row->variance,
            (string) $row->roll_up,
            $status,
            (string) $row->rule_id,
            $row->tag ?? '',
            implode(', ', (array) json_decode((string) $row->payment_ids, true)),
            $masked ? (string) PersonalData::maskPhone($row->customer_phone) : (string) ($row->customer_phone ?? ''),
            $masked ? implode(', ', array_map(fn (string $p): string => (string) PersonalData::maskPhone(trim($p)), array_filter(explode(',', $phones)))) : $phones,
            $row->region ?? '',
            $row->agent_id ?? '',
        ]);
    }
}
