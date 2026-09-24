<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;

it('alerts run-alert holders when a run is blocked by missing data', function (): void {
    reconcile('2026-09-22');

    $analyst = demoUser('analyst@demo');
    $auditor = demoUser('auditor@demo');
    expect($analyst->notifications()->first()?->data)->toMatchArray(['kind' => 'run_blocked', 'title' => 'Reconciliation blocked for 2026-09-22'])
        ->and($auditor->notifications()->count())->toBe(0);
});

it('sends one aggregated critical-exception alert per run to Finance Managers', function (): void {
    importAnswerKeyFiles('golden');
    app(MockSourceStore::class)->replaceDay(SourceType::Payments, '2026-09-21', []);
    reconcile();

    $manager = demoUser('manager@demo');
    $critical = $manager->notifications()->get()->filter(fn ($n) => $n->data['kind'] === 'critical_exceptions');
    $count = ReconException::query()->where('severity', 'critical')->count();

    expect($critical)->toHaveCount($count > 0 ? 1 : 0)
        ->and(demoUser('analyst@demo')->notifications()->get()->where('data.kind', 'critical_exceptions'))->toHaveCount(0);
});

it('sends the daily summary with aggregates only, including to Slack when configured', function (): void {
    Http::fake();
    config(['notifications.slack.webhook_url' => 'https://hooks.slack.test/abc']);
    importAnswerKeyFiles('golden');
    reconcile();

    $this->artisan('reconflow:daily-summary', ['--date' => '2026-09-22'])->assertSuccessful();

    $summary = demoUser('manager@demo')->notifications()->get()->firstWhere('data.kind', 'daily_summary');
    expect($summary?->data['title'])->toBe('Daily reconciliation summary for 2026-09-22')
        ->and($summary?->data['body'])->toContain('matched across 65 items');
    Http::assertSent(function ($request): bool {
        $body = (string) json_encode($request->data());

        return str_contains($body, 'Daily reconciliation summary') && preg_match('/(?:254|0)7\d{8}/', $body) === 0;
    });
});

it('lets users read and clear only their own notifications', function (): void {
    reconcile('2026-09-22');
    $analyst = demoUser('analyst@demo');
    $id = $analyst->notifications()->value('id');

    $this->actingAs($analyst)->getJson(route('notifications.index'))->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.kind', 'run_blocked');
    $this->actingAs(demoUser('auditor@demo'))->postJson(route('notifications.read', $id))->assertNotFound();
    $this->actingAs($analyst)->postJson(route('notifications.read', $id))->assertOk()->assertJsonPath('unread', 0);
    $this->actingAs($analyst)->postJson(route('notifications.read-all'))->assertOk();
});
