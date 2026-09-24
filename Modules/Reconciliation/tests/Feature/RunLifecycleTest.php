<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Audit\Models\AuditEvent;
use Modules\Ingestion\Actions\SeedDemoData;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\PaymentRecord;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Reconciliation\Actions\ReconcileDate;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Enums\RunTrigger;
use Modules\Reconciliation\Models\ItemStateRecord;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\RuleConfigService;
use Modules\Reconciliation\Support\Cents;

it('creates a new version on every re-run, supersedes the previous one and keeps its history', function (): void {
    importAnswerKeyFiles('golden');
    $first = reconcile();
    $second = reconcile();

    expect([$first->version, $second->version])->toBe([1, 2])
        ->and($first->fresh()->superseded_by_id)->toBe($second->id)
        ->and(runItems($second))->toBe(runItems($first))
        ->and(ReconResult::query()->where('run_id', $first->id)->count())->toBe(65);
});

it('stores the exact batch versions it reconciled', function (): void {
    importAnswerKeyFiles('golden');
    $run = reconcile();
    $active = SourceBatch::query()->active()->get()->keyBy(fn ($b) => $b->source->value);

    foreach (['sales', 'payments', 'postings'] as $source) {
        expect($run->batches[$source]['id'])->toBe($active[$source]->id)
            ->and($run->batches[$source]['version'])->toBe($active[$source]->version);
    }
});

it('blocks a closed date when a source has no data', function (): void {
    test()->actingAs(demoUser('analyst@demo'));
    test()->post(route('ingestion.uploads.confirm', stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales')));

    $run = reconcile();

    expect($run->status->value)->toBe('blocked_data')
        ->and($run->blocked_reason)->toBe('No data for: payments, postings')
        ->and($run->results()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'run.blocked')->exists())->toBeTrue();
});

it('runs an open date as provisional even with missing sources', function (): void {
    $today = CarbonImmutable::now('Africa/Nairobi')->toDateString();
    test()->actingAs(demoUser('analyst@demo'));
    $staging = stageFile(samplePath('golden/csv/sales_2026-09-22.csv'), 'sales', $today);

    $run = reconcile($today);

    expect($staging->rows_invalid)->toBe(62)
        ->and($run->provisional)->toBeTrue()
        ->and($run->status->value)->toBe('completed');
});

it('marks the next day stale when an earlier day is re-run', function (): void {
    app(SeedDemoData::class)->handle(2, 150, 9, '2026-09-22', ingest: true);
    $dayTwo = ReconRun::query()->forDate('2026-09-22')->latestCompleted()->firstOrFail();
    expect($dayTwo->stale_at)->toBeNull();

    reconcile('2026-09-21');

    expect($dayTwo->fresh()->stale_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'run.marked_stale')->exists())->toBeTrue();
});

it('carries a pending-timing sale into the next run and clears it when paid next day', function (): void {
    [, $store] = seedPendingSale();
    $store->replaceDay(SourceType::Payments, '2026-09-22', [
        ['payment_id' => 'SNEXTDAY01', 'timestamp' => '2026-09-22 08:15:00', 'channel' => 'MOBILE_MONEY', 'payer_phone' => '254700990001', 'amount' => '28.00', 'currency' => 'USD', 'reference' => 'TUP-S-990001'],
        ['payment_id' => 'STODAY0001', 'timestamp' => '2026-09-22 10:10:00', 'channel' => 'MOBILE_MONEY', 'payer_phone' => '254700990001', 'amount' => '28.00', 'currency' => 'USD', 'reference' => 'TUP-S-990002'],
    ]);

    $dayOne = reconcile('2026-09-21', refresh: true);
    $pending = $dayOne->results()->where('status', 'PENDING_TIMING')->firstOrFail();
    $dayTwo = reconcile('2026-09-22', refresh: true);

    $cleared = $dayTwo->results()->where('section', 'prior_day')->firstOrFail();
    expect($cleared->status->value)->toBe('MATCHED_PRIOR_DAY')
        ->and($cleared->tag)->toBe('Paid next day')
        ->and($cleared->prior_result_id)->toBe($pending->id)
        ->and($dayTwo->summary['prior_day_cleared'])->toBe(['count' => 1, 'value' => '28.00'])
        ->and($dayTwo->summary['sale_items'])->toBe(1)
        ->and($dayTwo->summary['match_rate'])->toBe('100.00')
        ->and(ItemStateRecord::query()->find($pending->id)?->state->value)->toBe('resolved')
        ->and($pending->fresh()->status->value)->toBe('PENDING_TIMING')
        ->and(AuditEvent::query()->where('action', 'item.resolved')->exists())->toBeTrue();
});

it('escalates a carried sale to missing payment when the next day brings no payment, and recomputes on re-run', function (): void {
    [, $store] = seedPendingSale();
    $store->replaceDay(SourceType::Payments, '2026-09-22', [
        ['payment_id' => 'STODAY0001', 'timestamp' => '2026-09-22 10:10:00', 'channel' => 'MOBILE_MONEY', 'payer_phone' => '254700990001', 'amount' => '28.00', 'currency' => 'USD', 'reference' => 'TUP-S-990002'],
    ]);
    $pending = reconcile('2026-09-21', refresh: true)->results()->where('status', 'PENDING_TIMING')->firstOrFail();

    reconcile('2026-09-22', refresh: true);
    $state = ItemStateRecord::query()->findOrFail($pending->id);
    expect($state->state->value)->toBe('escalated')
        ->and($state->effective_status->value)->toBe('MISSING_PAYMENT')
        ->and($state->reason)->toBe('no payment by close of D+1 window');

    reconcile('2026-09-22');
    expect(ItemStateRecord::query()->where('result_id', $pending->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'item.reopened')->exists())->toBeTrue();
});

it('never reports a payment twice across consecutive days', function (): void {
    app(SeedDemoData::class)->handle(3, 400, 21, '2026-09-22', ingest: true);
    $runs = ReconRun::query()->latestCompleted()->orderBy('business_date')->get();
    expect($runs)->toHaveCount(3);

    $observed = [];
    foreach ($runs as $run) {
        foreach (ReconResult::query()->where('run_id', $run->id)->pluck('payment_identities') as $identities) {
            foreach ($identities as $identity) {
                $observed[] = $identity;
            }
        }
    }

    $expected = [];
    foreach ($runs as $run) {
        $batchId = $run->batches['payments']['id'];
        foreach (PaymentRecord::query()->where('batch_id', $batchId)->whereDate('payment_date', $run->business_date->toDateString())->get() as $p) {
            $expected[] = implode('|', [$p->payment_id, $p->paid_at->getTimestamp(), Cents::fromDecimal((string) $p->amount), $p->reference ?? '']);
        }
    }
    $graceOfLastDay = array_diff($observed, $expected);
    $lastDayEnd = CarbonImmutable::parse('2026-09-23', 'Africa/Nairobi')->getTimestamp();

    sort($expected);
    $ownDateObserved = array_values(array_diff($observed, $graceOfLastDay));
    sort($ownDateObserved);
    expect($ownDateObserved)->toBe($expected);
    foreach ($graceOfLastDay as $identity) {
        expect((int) explode('|', $identity)[1])->toBeGreaterThanOrEqual($lastDayEnd);
    }
});

it('reconciles every seeded day when demo data is generated', function (): void {
    app(SeedDemoData::class)->handle(2, 100, 3, '2026-09-22');

    expect(ReconRun::query()->latestCompleted()->count())->toBe(2)
        ->and(ReconRun::query()->pluck('trigger')->map->value->unique()->values()->all())->toBe(['demo']);
});

it('respects a manual upload on refresh unless replacing it is chosen', function (): void {
    app(SeedDemoData::class)->handle(1, 50, 4, '2026-09-22', ingest: false);
    importAnswerKeyFiles('golden');

    $kept = reconcile(refresh: true);
    expect($kept->batches['sales']['manual'])->toBeTrue()->and(runItems($kept))->toBe(answerKeyItems('golden'));

    $replaced = app(ReconcileDate::class)->handle(
        new RunRequest('2026-09-22', RunTrigger::Manual, true, ['sales', 'payments', 'postings']),
        'test',
    );
    expect($replaced->batches['sales']['manual'])->toBeFalse()->and($replaced->batches['sales']['mode'])->toBe('pull');
});

it('versions rule settings and audits every change', function (): void {
    $service = app(RuleConfigService::class);
    $service->create(['tolerance' => '1.00'], demoUser('admin@demo'), 'Wider tolerance');
    $run = (function () {
        importAnswerKeyFiles('golden');

        return reconcile();
    })();

    expect($service->current()->tolerance)->toBe('1.00')
        ->and($run->rule_config['tolerance'])->toBe('1.00')
        ->and($run->rule_config['version'])->toBe(2)
        ->and(AuditEvent::query()->where('action', 'config.created')->count())->toBe(2);
});
