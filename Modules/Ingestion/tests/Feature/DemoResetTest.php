<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Audit\Models\AuditEvent;
use Modules\Audit\Services\ChainVerifier;
use Modules\Ingestion\Actions\SeedDemoData;
use Modules\Ingestion\Jobs\SeedDemoDataJob;
use Modules\Ingestion\Models\MockSourceRow;
use Modules\Ingestion\Models\SourceBatch;

it('lets an administrator reset demo data with an explicit confirmation, keeping the audit log intact', function (): void {
    Queue::fake();
    app(SeedDemoData::class)->handle(1, 50, 1, '2026-09-22');
    $auditBefore = AuditEvent::query()->count();
    $admin = demoUser('admin@demo');

    $this->actingAs($admin)->post(route('ingestion.demo.reset'), ['confirmation' => 'nope'])->assertSessionHasErrors('confirmation');
    $this->actingAs($admin)->post(route('ingestion.demo.reset'), ['confirmation' => 'RESET'])->assertRedirect(route('ingestion.uploads.index'));

    expect(SourceBatch::query()->count())->toBe(0)
        ->and(MockSourceRow::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBeGreaterThan($auditBefore)
        ->and(AuditEvent::query()->where('action', 'demo.reset')->exists())->toBeTrue()
        ->and(app(ChainVerifier::class)->verify()->ok)->toBeTrue();
    Queue::assertPushed(SeedDemoDataJob::class);
});

it('forbids demo reset to anyone without demo.reset', function (): void {
    $this->actingAs(demoUser('manager@demo'))->post(route('ingestion.demo.reset'), ['confirmation' => 'RESET'])->assertForbidden();
});

it('queues first-boot demo data only when the database is empty and auto-seed is on', function (): void {
    Queue::fake();
    config(['ingestion.demo.auto_seed' => true]);

    $this->artisan('reconflow:bootstrap-demo')->assertSuccessful();
    Queue::assertPushed(SeedDemoDataJob::class, 1);

    app(SeedDemoData::class)->handle(1, 10, 1, '2026-09-22', ingest: false);
    $this->artisan('reconflow:bootstrap-demo')->assertSuccessful();
    Queue::assertPushed(SeedDemoDataJob::class, 1);
});
