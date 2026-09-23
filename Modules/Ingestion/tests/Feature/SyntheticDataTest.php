<?php

declare(strict_types=1);

use Modules\Ingestion\Actions\SeedDemoData;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\QuarantinedRow;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Services\MockSourceStore;
use Modules\Ingestion\Services\SyntheticDataGenerator;

it('is deterministic: the same seed produces exactly the same data', function (): void {
    $a = (new SyntheticDataGenerator(42))->day('2026-09-22', 500);
    $b = (new SyntheticDataGenerator(42))->day('2026-09-22', 500);
    $c = (new SyntheticDataGenerator(43))->day('2026-09-22', 500);

    expect($a)->toEqual($b)->and($a)->not->toEqual($c);
});

it('injects every configured anomaly type', function (): void {
    $day = (new SyntheticDataGenerator(7))->day('2026-09-22', 3000);
    $payments = collect($day->payments);
    $salesById = collect($day->sales)->keyBy('transaction_id');
    $postedIds = collect($day->postings)->pluck('transaction_id')->flip();

    $byReference = $payments->groupBy('reference');
    expect($byReference->filter(fn ($group, $ref) => $ref !== '' && $salesById->has($ref) && $group->count() > 1)->count())->toBeGreaterThan(20)
        ->and($payments->filter(fn ($p) => $p['reference'] === null || str_starts_with((string) $p['reference'], 'TUPS') || strlen((string) $p['reference']) === 11)->count())->toBeGreaterThan(40)
        ->and($payments->filter(fn ($p) => str_starts_with((string) $p['reference'], 'ACC '))->count())->toBeGreaterThan(3)
        ->and($payments->where('channel', 'CASH')->count())->toBeGreaterThan(0)
        ->and($payments->filter(fn ($p) => str_contains((string) $p['timestamp'], '25:61'))->count())->toBeGreaterThan(0)
        ->and($payments->filter(fn ($p) => str_starts_with((string) $p['timestamp'], '2026-09-23'))->count())->toBeGreaterThan(0)
        ->and($salesById->reject(fn ($s) => $s['transaction_id'] === null)->filter(fn ($s, $id) => ! $postedIds->has($id))->count())->toBeGreaterThan(15)
        ->and(collect($day->sales)->where('transaction_id', null)->count())->toBeGreaterThan(0)
        ->and(collect($day->postings)->where('currency', 'KES')->count())->toBeGreaterThan(0);
});

it('produces right-skewed small-ticket amounts', function (): void {
    $amounts = collect((new SyntheticDataGenerator(1))->day('2026-09-22', 3000)->sales)
        ->pluck('expected_amount')->filter(fn ($a) => ! str_starts_with((string) $a, '-'))->map(fn ($a) => (float) $a)->sort()->values();

    expect($amounts->first())->toBeGreaterThan(0)
        ->and($amounts->last())->toBeLessThanOrEqual(2500)
        ->and($amounts[(int) ($amounts->count() / 2)])->toBeLessThan($amounts->avg());
});

it('seeds the simulated source systems and ingests every source for every day', function (): void {
    $dates = app(SeedDemoData::class)->handle(3, 200, 42, '2026-09-22');

    expect($dates)->toBe(['2026-09-20', '2026-09-21', '2026-09-22'])
        ->and(SourceBatch::query()->count())->toBe(9)
        ->and(SourceBatch::query()->where('source', 'sales')->sum('rows_loaded'))->toBe(600)
        ->and(QuarantinedRow::query()->count())->toBeGreaterThan(0);
});

it('assigns late-night payments to the extract window of the previous business date', function (): void {
    app(SeedDemoData::class)->handle(2, 3000, 3, '2026-09-22', ingest: false);
    $store = app(MockSourceStore::class);
    $grace = collect($store->extract(SourceType::Payments, '2026-09-21'))
        ->filter(fn ($p) => str_starts_with((string) $p['timestamp'], '2026-09-22 0') && (int) substr((string) $p['timestamp'], 11, 2) < 6);

    expect($grace)->not->toBeEmpty()
        ->and(collect($store->extract(SourceType::Payments, '2026-09-22'))->pluck('payment_id'))->toContain($grace->first()['payment_id']);
});

it('exports a seeded day to template-format files that upload cleanly', function (): void {
    app(SeedDemoData::class)->handle(1, 300, 5, '2026-09-22');
    $directory = sys_get_temp_dir().'/export-'.uniqid();

    $this->artisan('reconflow:seed', ['--export' => '2026-09-22', '--out' => $directory])->assertSuccessful();

    $this->actingAs(demoUser('analyst@demo'));
    foreach (['sales' => 'sales', 'payments' => 'payments', 'postings' => 'erp_postings'] as $source => $prefix) {
        $staging = stageFile("{$directory}/{$prefix}_2026-09-22.xlsx", $source);
        $batch = SourceBatch::query()->active()->where('source', $source)->firstOrFail();
        expect($staging->header_check['ok'])->toBeTrue()
            ->and($staging->rows_valid)->toBe($batch->rows_loaded)
            ->and($staging->rows_invalid)->toBe($batch->rows_quarantined);
    }
});
