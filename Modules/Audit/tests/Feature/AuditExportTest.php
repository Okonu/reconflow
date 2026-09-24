<?php

declare(strict_types=1);

use Modules\Audit\Models\AuditEvent;

it('exports the filtered audit log as CSV and audits the export', function (): void {
    $csv = $this->actingAs(demoUser('auditor@demo'))->get(route('audit.export', ['action' => 'user.']))->assertOk()->streamedContent();

    expect($csv)->toStartWith('id,occurred_at,actor,action')
        ->and($csv)->toContain('user.seeded')
        ->and($csv)->not->toContain('role.seeded')
        ->and(AuditEvent::query()->where('action', 'audit.exported')->exists())->toBeTrue();
});

it('keeps the date filter stable across page loads', function (): void {
    $this->actingAs(demoUser('auditor@demo'))->get(route('audit.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.to', '2026-09-30'));
});
