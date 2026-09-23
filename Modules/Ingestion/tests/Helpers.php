<?php

declare(strict_types=1);

use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\QuarantinedRow;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Support\Schema\ValidationContext;
use PhpOffice\PhpSpreadsheet\IOFactory;

function sale(array $overrides = []): array
{
    return array_merge([
        'transaction_id' => 'TUP-S-000001', 'business_date' => '2026-09-22', 'timestamp' => '2026-09-22 12:00:24',
        'agent_id' => 'AG-016', 'customer_phone' => '254700321819', 'region' => 'Central', 'product_sku' => 'FERT-CAN-50KG',
        'expected_amount' => '31.00', 'currency' => 'USD', 'payment_reference' => 'TUP-S-000001',
    ], $overrides);
}

function payment(array $overrides = []): array
{
    return array_merge([
        'payment_id' => 'SCNO6B9M80', 'timestamp' => '2026-09-22 12:08:25', 'channel' => 'MOBILE_MONEY',
        'payer_phone' => '254700321819', 'amount' => '31.00', 'currency' => 'USD', 'reference' => 'TUP-S-000001',
    ], $overrides);
}

function errorsFor(SourceType $source, array $row): array
{
    return $source->schema()->validate(2, $row, ValidationContext::forDate('2026-09-22'))->errors;
}

function stageFile(string $path, string $source, string $date = '2026-09-22', ?string $name = null): UploadStaging
{
    $response = test()->post(route('ingestion.uploads.store'), [
        'source' => $source,
        'business_date' => $date,
        'file' => uploadedCopy($path, $name),
    ])->assertRedirect();
    $id = basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));

    return UploadStaging::query()->findOrFail($id);
}

function answerKeyQuarantine(string $answerKey): array
{
    $sheet = IOFactory::load($answerKey)->getSheetByName('Quarantine');
    $rows = [];
    foreach (array_slice($sheet->toArray(), 1) as [$source, $key, $reason]) {
        if ($source !== null) {
            $rows[] = [$source, (string) $key, $reason];
        }
    }
    sort($rows);

    return $rows;
}

function quarantinedAsAnswerKey(): array
{
    $rows = QuarantinedRow::query()->get()
        ->map(fn ($q) => [$q->source->value, $q->record_key, implode('; ', $q->reasons)])
        ->all();
    sort($rows);

    return $rows;
}
