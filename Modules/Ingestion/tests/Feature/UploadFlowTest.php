<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Audit\Models\AuditEvent;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SalesRecord;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Services\TemplateBuilder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(fn () => $this->actingAs(demoUser('analyst@demo')));

function downloadTemplate(string $source): string
{
    $response = test()->get(route('ingestion.uploads.template', $source))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    copy($response->baseResponse->getFile()->getPathname(), $path);

    return $path;
}

it('round-trips a downloaded template: fill it in, upload it, and every row is valid', function (): void {
    $path = downloadTemplate('sales');
    $book = IOFactory::load($path);
    $sheet = $book->getSheetByName('Data');
    $sheet->fromArray([
        ['TUP-S-000123', '2026-09-22', '2026-09-22 10:15:00', 'AG-014', '254700123456', 'Western', 'FERT-DAP-50KG', 77, 'USD', 'TUP-S-000123'],
        ['TUP-S-000124', '2026-09-22', '2026-09-22 11:15:00', 'AG-015', '254700123457', 'Coast', 'SOLAR-LAMP-S1', 24.5, 'USD', null],
    ], null, 'A2');
    IOFactory::createWriter($book, 'Xlsx')->save($path);

    $staging = stageFile($path, 'sales');

    expect($staging->header_check['ok'])->toBeTrue()
        ->and([$staging->rows_read, $staging->rows_valid, $staging->rows_invalid])->toBe([2, 2, 0]);
});

it('generates templates from the schema with the reference layout', function (string $source, string $reference): void {
    $generated = IOFactory::load(downloadTemplate($source));
    $expected = IOFactory::load(samplePath("templates/{$reference}"));

    expect($generated->getSheetNames())->toBe(['Data', 'Instructions'])
        ->and($generated->getSheetByName('Data')->rangeToArray('A1:J1')[0])->toBe($expected->getSheetByName('Data')->rangeToArray('A1:J1')[0])
        ->and($generated->getSheetByName('Data')->getFreezePane())->toBe('A2')
        ->and($generated->getSheetByName('Data')->getStyle('A1')->getFill()->getStartColor()->getRGB())->toBe('2D7F67')
        ->and($generated->getSheetByName('Instructions')->getCell('A11')->getValue())->toBe('Column');
})->with([
    ['sales', 'sales_upload_template.xlsx'],
    ['payments', 'payments_upload_template.xlsx'],
    ['postings', 'erp_postings_upload_template.xlsx'],
]);

it('builds each template within a web request memory budget with column-wide formats', function (SourceType $source): void {
    memory_reset_peak_usage();
    $before = memory_get_usage(true);

    $path = app(TemplateBuilder::class)->build($source);

    expect(memory_get_peak_usage(true) - $before)->toBeLessThan(48 * 1024 * 1024)
        ->and(filesize($path))->toBeLessThan(100 * 1024);

    $sheet = IOFactory::load($path)->getSheetByName('Data');
    foreach ($source->schema()->columns() as $index => $column) {
        $letter = Coordinate::stringFromColumnIndex($index + 1);
        $xf = $sheet->getParent()->getCellXfByIndex($sheet->getColumnDimension($letter)->getXfIndex() ?? 0);
        expect($xf->getNumberFormat()->getFormatCode())->toBe($column->type->excelNumberFormat());
    }
})->with(SourceType::cases());

it('blocks a file whose columns do not match the template and refuses to confirm it', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, "transaction_id,business_day,amount\nTUP-S-1,2026-09-22,10\n");

    $staging = stageFile($path, 'sales', name: 'wrong.csv');

    expect($staging->header_check['ok'])->toBeFalse()
        ->and($staging->header_check['message'])->toContain('Missing columns: business_date')
        ->and($staging->header_check['message'])->toContain('Unexpected columns: business_day, amount');
    $this->post(route('ingestion.uploads.confirm', $staging))->assertSessionHas('error');
    expect(SourceBatch::query()->count())->toBe(0);
});

it('rejects macro-enabled, unknown and mislabelled files', function (string $name, string $content): void {
    $path = tempnam(sys_get_temp_dir(), 'bad');
    file_put_contents($path, $content === 'golden' ? file_get_contents(samplePath('golden/sales_2026-09-22.xlsx')) : $content);

    $this->post(route('ingestion.uploads.store'), [
        'source' => 'sales', 'business_date' => '2026-09-22', 'file' => uploadedCopy($path, $name),
    ])->assertSessionHas('error');

    expect(UploadStaging::query()->count())->toBe(0);
})->with([
    'xlsm' => ['sales.xlsm', 'golden'],
    'xls' => ['sales.xls', 'golden'],
    'csv text named xlsx' => ['sales.xlsx', "transaction_id\nTUP-S-1\n"],
    'workbook named csv' => ['sales.csv', 'golden'],
]);

it('rejects a workbook that contains a VBA project even with an xlsx extension', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'vba').'.xlsx';
    copy(samplePath('golden/sales_2026-09-22.xlsx'), $path);
    $zip = new ZipArchive;
    $zip->open($path);
    $zip->addFromString('xl/vbaProject.bin', 'macro');
    $zip->close();

    $this->post(route('ingestion.uploads.store'), [
        'source' => 'sales', 'business_date' => '2026-09-22', 'file' => uploadedCopy($path, 'sales.xlsx'),
    ])->assertSessionHas('error');
});

it('keeps staged rows out of the live tables until the upload is confirmed', function (): void {
    $staging = stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales');
    expect(SalesRecord::query()->count())->toBe(0)->and(SourceBatch::query()->count())->toBe(0);

    $this->post(route('ingestion.uploads.confirm', $staging))->assertRedirect();

    expect(SalesRecord::query()->count())->toBe(59);
});

it('shows the preview with per-row status and an invalid-only filter, masking phone numbers', function (): void {
    $staging = stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales');

    $this->get(route('ingestion.uploads.show', ['staging' => $staging, 'invalid_only' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Ingestion/Uploads/Preview')
            ->where('pagination.total', 3)
            ->has('rows', 3)
            ->where('rows.0.errors', ['Invalid phone format (expected 2547XXXXXXXX)'])
            ->where('rows.2.status', 'invalid')
            ->where('rows.2.errors', ['Missing required field: transaction_id'])
            ->where('rows.2.values.customer_phone', '07•• ••• 161'));
});

it('warns when the same file was already imported for that date', function (): void {
    $first = stageFile(samplePath('golden/erp_postings_2026-09-22.xlsx'), 'postings');
    $this->post(route('ingestion.uploads.confirm', $first));

    $second = stageFile(samplePath('golden/erp_postings_2026-09-22.xlsx'), 'postings');

    expect($second->duplicate_of_batch_id)->toBe($first->fresh()->batch_id)
        ->and($second->date_has_data)->toBeTrue();
});

it('requires Replace or Append when the date already has data, and Replace creates a new batch version', function (): void {
    $this->post(route('ingestion.uploads.confirm', stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales')));
    $again = stageFile(samplePath('golden/csv/sales_2026-09-22.csv'), 'sales');

    $this->post(route('ingestion.uploads.confirm', $again))->assertSessionHas('error', 'Data already exists for this date. Choose Replace or Append.');
    $this->post(route('ingestion.uploads.confirm', $again), ['mode' => 'replace'])->assertRedirect();

    $batches = SourceBatch::query()->orderBy('version')->get();
    expect($batches->pluck('version')->all())->toBe([1, 2])
        ->and($batches->pluck('status.value')->all())->toBe(['superseded', 'active'])
        ->and($batches[0]->superseded_by_id)->toBe($batches[1]->id)
        ->and(SalesRecord::query()->whereIn('batch_id', SourceBatch::query()->active()->pluck('id'))->count())->toBe(59)
        ->and(AuditEvent::query()->where('action', 'batch.superseded')->exists())->toBeTrue();
});

it('refuses rows whose keys already exist when appending', function (): void {
    $this->post(route('ingestion.uploads.confirm', stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales')));
    $again = stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales');

    $this->get(route('ingestion.uploads.show', $again))->assertInertia(fn (Assert $page) => $page->where('append_conflicts', 59));
    $this->post(route('ingestion.uploads.confirm', $again), ['mode' => 'append']);

    $appended = SourceBatch::query()->latest('id')->firstOrFail();
    expect($appended->mode->value)->toBe('upload_append')
        ->and($appended->rows_added)->toBe(0)
        ->and($appended->rows_loaded)->toBe(59)
        ->and($appended->dq_summary['reasons'])->toHaveKey('transaction_id TUP-S-000001 already exists for this date: use Replace');
});

it('cancels a staged upload without importing anything', function (): void {
    $staging = stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales');

    $this->post(route('ingestion.uploads.cancel', $staging))->assertRedirect(route('ingestion.uploads.index'));

    expect($staging->fresh()->state->value)->toBe('cancelled')
        ->and(SourceBatch::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'upload.cancelled')->exists())->toBeTrue();
});

it('expires stale staged uploads, audits the purge and refuses to confirm them', function (): void {
    $staging = stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales');
    $staging->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->post(route('ingestion.uploads.confirm', $staging))->assertSessionHas('error');
    $this->artisan('ingestion:purge-staging')->assertSuccessful();

    expect($staging->fresh()->state->value)->toBe('expired')
        ->and($staging->fresh()->rows)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'upload.expired')->exists())->toBeTrue();
});

it('audits staging and confirmation', function (): void {
    $this->post(route('ingestion.uploads.confirm', stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales')));

    expect(AuditEvent::query()->pluck('action')->all())->toContain('upload.staged', 'upload.confirmed');
});

it('lets auditors see upload history but not upload', function (): void {
    $this->actingAs(demoUser('auditor@demo'));

    $this->get(route('ingestion.uploads.index'))->assertInertia(fn (Assert $page) => $page->where('can.upload', false));
    $this->post(route('ingestion.uploads.store'), ['source' => 'sales', 'business_date' => '2026-09-22'])->assertForbidden();
});

it('downloads the sample test pack with the golden files and templates', function (): void {
    $response = $this->get(route('ingestion.uploads.sample-pack'))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    expect($names)->toContain('reconflow-sample-pack/golden/expected_results_2026-09-22.xlsx', 'reconflow-sample-pack/templates/sales_upload_template.xlsx');
});
