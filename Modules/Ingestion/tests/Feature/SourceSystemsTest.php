<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Http;
use Modules\Ingestion\Actions\IngestFromSourceSystem;
use Modules\Ingestion\Actions\SeedDemoData;
use Modules\Ingestion\Connectors\MockSourceApiConnector;
use Modules\Ingestion\Connectors\SourceConnector;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SourceBatch;

beforeEach(fn () => app(SeedDemoData::class)->handle(1, 100, 11, '2026-09-22', ingest: false));

it('requires the source-system token on the simulated APIs', function (): void {
    $this->getJson('/api/mock/sales?date=2026-09-22')->assertUnauthorized();
    $this->getJson('/api/mock/sales?date=2026-09-22', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
});

it('serves an extract labelled as a simulated source system', function (): void {
    $this->getJson('/api/mock/payments?date=2026-09-22', ['Authorization' => 'Bearer test-source-token'])
        ->assertOk()
        ->assertJsonPath('simulated', true)
        ->assertJsonPath('system', 'simulated-payments')
        ->assertJsonPath('window.from', '2026-09-22T00:00:00+03:00')
        ->assertJsonPath('window.to', '2026-09-23T06:00:00+03:00')
        ->assertJsonStructure(['rows' => [['payment_id', 'timestamp', 'channel', 'payer_phone', 'amount', 'currency', 'reference']]]);
});

it('ingests through the HTTP connector exactly as through the in-process connector', function (): void {
    $body = $this->getJson('/api/mock/sales?date=2026-09-22', ['Authorization' => 'Bearer test-source-token'])->json();
    Http::fake(['sources.test/api/mock/sales*' => Http::response($body)]);
    app()->bind(SourceConnector::class, MockSourceApiConnector::class);

    $batch = app(IngestFromSourceSystem::class)->handle(SourceType::Sales, '2026-09-22');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-source-token') && str_contains($request->url(), 'date=2026-09-22'));
    expect($batch->origin->value)->toBe('source_system')->and($batch->rows_received)->toBe(count($body['rows']));
});

it('reports an unreachable source system clearly', function (): void {
    Http::fake(['sources.test/*' => Http::response('down', 503)]);
    app()->bind(SourceConnector::class, MockSourceApiConnector::class);

    expect(fn () => app(IngestFromSourceSystem::class)->handle(SourceType::Sales, '2026-09-22'))
        ->toThrow(DomainException::class, 'returned HTTP 503');
    expect(SourceBatch::query()->count())->toBe(0);
});

it('versions repeated pulls and supersedes the previous batch', function (): void {
    app(IngestFromSourceSystem::class)->handle(SourceType::Sales, '2026-09-22');
    app(IngestFromSourceSystem::class)->handle(SourceType::Sales, '2026-09-22');

    expect(SourceBatch::query()->orderBy('version')->get()->pluck('status.value')->all())->toBe(['superseded', 'active']);
});

it('exposes batches and their data-quality report as JSON for API clients', function (): void {
    app(IngestFromSourceSystem::class)->handle(SourceType::Sales, '2026-09-22');
    $this->actingAs(demoUser('auditor@demo'));
    $batch = SourceBatch::query()->firstOrFail();

    $this->getJson(route('ingestion.batches.index', ['business_date' => '2026-09-22']))
        ->assertOk()->assertJsonPath('data.0.source', 'sales')->assertJsonPath('data.0.rows_loaded', $batch->rows_loaded);
    $this->getJson(route('ingestion.batches.show', $batch))
        ->assertOk()->assertJsonPath('batch.rows_quarantined', $batch->rows_quarantined)->assertJsonStructure(['quarantined_rows']);
});
