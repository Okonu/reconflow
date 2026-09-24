<?php

declare(strict_types=1);

use Modules\Audit\Models\AuditEvent;
use Modules\Ingestion\Models\SalesRecord;

beforeEach(function (): void {
    importAnswerKeyFiles('golden');
    reconcile();
});

it('shows the report with roll-up counts, filters and masked phones', function (): void {
    $this->actingAs(demoUser('auditor@demo'))->get(route('reports.reconciliation', ['date' => '2026-09-22', 'roll_up' => 'Variance']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reconciliation/Report/Index')
            ->where('rows.total', 6)
            ->where('counts.Variance', 6)
            ->where('rows.data.0.customer_phone', fn ($p) => str_contains((string) $p, '•'))
            ->where('can.export_unmasked', false));
});

it('exports masked CSV with formula-safe cells and audits the export', function (): void {
    SalesRecord::query()->where('transaction_id', 'TUP-S-000041')->update(['region' => '=HYPERLINK("x")']);

    $response = $this->actingAs(demoUser('analyst@demo'))->get(route('reports.reconciliation.export', ['date' => '2026-09-22', 'format' => 'csv']))->assertOk();
    $csv = $response->streamedContent();
    $phone = (string) SalesRecord::query()->where('transaction_id', 'TUP-S-000041')->value('customer_phone');

    expect($csv)->toStartWith('"Transaction ID",')
        ->and($csv)->not->toContain($phone)
        ->and($csv)->toContain("'=HYPERLINK")
        ->and(substr_count(trim($csv), "\n"))->toBe(65)
        ->and(AuditEvent::query()->where('action', 'report.exported')->value('payload'))->toMatchArray(['masked' => true, 'format' => 'csv', 'rows' => 65]);
});

it('exports XLSX', function (): void {
    $this->actingAs(demoUser('analyst@demo'))->get(route('reports.reconciliation.export', ['date' => '2026-09-22', 'format' => 'xlsx']))
        ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('requires the unmasked-export permission and a reason, and audits the reason', function (): void {
    $this->actingAs(demoUser('analyst@demo'))->postJson(route('reports.reconciliation.export-unmasked'), ['date' => '2026-09-22', 'format' => 'csv', 'reason' => 'Need phones to call customers'])->assertForbidden();
    $this->actingAs(demoUser('manager@demo'))->postJson(route('reports.reconciliation.export-unmasked'), ['date' => '2026-09-22', 'format' => 'csv'])->assertUnprocessable();

    $csv = $this->actingAs(demoUser('manager@demo'))->post(route('reports.reconciliation.export-unmasked'), ['date' => '2026-09-22', 'format' => 'csv', 'reason' => 'Need phones to call customers'])->assertOk()->streamedContent();

    expect($csv)->toContain((string) SalesRecord::query()->value('customer_phone'))
        ->and(AuditEvent::query()->where('action', 'report.exported')->value('payload'))->toMatchArray(['masked' => false, 'reason' => 'Need phones to call customers']);
});
