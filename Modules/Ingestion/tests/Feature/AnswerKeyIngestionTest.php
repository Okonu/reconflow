<?php

declare(strict_types=1);

use Modules\Ingestion\Models\SourceBatch;

dataset('answer keys', [
    'golden xlsx' => ['golden', 'xlsx', [62, 59, 3], [63, 61, 2], [57, 56, 1]],
    'golden csv' => ['golden', 'csv', [62, 59, 3], [63, 61, 2], [57, 56, 1]],
    'volume xlsx' => ['volume', 'xlsx', [2503, 2500, 3], [2533, 2531, 2], [2477, 2476, 1]],
]);

function answerKeyFiles(string $set, string $format): array
{
    $suffix = $set === 'volume' ? '_volume' : '';
    $dir = $format === 'csv' ? "{$set}/csv" : $set;

    return [
        'sales' => samplePath("{$dir}/sales_2026-09-22{$suffix}.{$format}"),
        'payments' => samplePath("{$dir}/payments_2026-09-22{$suffix}.{$format}"),
        'postings' => samplePath("{$dir}/erp_postings_2026-09-22{$suffix}.{$format}"),
        'key' => samplePath("{$set}/expected_results_2026-09-22{$suffix}.xlsx"),
    ];
}

it('stages, previews and imports the provided files with the answer key quarantine', function (string $set, string $format, array $sales, array $payments, array $postings): void {
    $this->actingAs(demoUser('analyst@demo'));
    $files = answerKeyFiles($set, $format);

    foreach (['sales' => $sales, 'payments' => $payments, 'postings' => $postings] as $source => [$read, $valid, $invalid]) {
        $staging = stageFile($files[$source], $source);
        expect($staging->header_check['ok'])->toBeTrue()
            ->and([$staging->rows_read, $staging->rows_valid, $staging->rows_invalid])->toBe([$read, $valid, $invalid]);
        $this->post(route('ingestion.uploads.confirm', $staging))->assertRedirect();
    }

    expect(quarantinedAsAnswerKey())->toBe(answerKeyQuarantine($files['key']))
        ->and(SourceBatch::query()->active()->sum('rows_loaded'))->toBe($sales[1] + $payments[1] + $postings[1]);
})->with('answer keys');

it('produces identical staged results from the csv and xlsx versions of the golden files', function (string $source, string $file): void {
    $this->actingAs(demoUser('analyst@demo'));
    $xlsx = stageFile(samplePath("golden/{$file}.xlsx"), $source);
    $csv = stageFile(samplePath("golden/csv/{$file}.csv"), $source);

    $outcome = fn ($staging) => array_map(fn (array $row) => [$row['row'], $row['key'], $row['values'], $row['errors']], $staging->rows);

    expect($outcome($csv))->toBe($outcome($xlsx))
        ->and([$csv->rows_valid, $csv->rows_invalid])->toBe([$xlsx->rows_valid, $xlsx->rows_invalid]);
})->with([
    ['sales', 'sales_2026-09-22'],
    ['payments', 'payments_2026-09-22'],
    ['postings', 'erp_postings_2026-09-22'],
]);
