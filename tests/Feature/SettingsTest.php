<?php

declare(strict_types=1);

use Modules\Adjustments\Models\Adjustment;
use Modules\AI\Services\AiSettings;
use Modules\Audit\Models\AuditEvent;
use Modules\ExceptionManagement\Models\ReconException;

it('lists the settings sections a user can view and who may edit them', function (): void {
    $this->actingAs(demoUser('auditor@demo'))->get(route('settings.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings')
            ->where('sections.0.key', 'rules')
            ->where('sections.0.can_manage', false));
    $this->actingAs(demoUser('admin@demo'))->get(route('settings.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('sections.0.can_manage', true)->has('sections', 4));
});

it('versions and audits a change with a reason, and validates band order', function (): void {
    $admin = demoUser('admin@demo');
    $values = ['medium_from' => '50.00', 'high_from' => '400.00', 'critical_from' => '1500.00', 'sla_low_hours' => 120, 'sla_medium_hours' => 48, 'sla_high_hours' => 24, 'sla_critical_hours' => 8];

    $this->actingAs($admin)->put(route('settings.update', 'workflow'), [...$values, 'high_from' => '10.00', 'comment' => 'Tighter bands'])->assertSessionHasErrors('high_from');
    $this->actingAs($admin)->put(route('settings.update', 'workflow'), $values)->assertSessionHasErrors('comment');
    $this->actingAs($admin)->put(route('settings.update', 'workflow'), [...$values, 'comment' => 'Tighter bands for Q4'])->assertSessionHas('success');

    expect(AuditEvent::query()->where('action', 'settings.workflow_changed')->value('payload'))->toMatchArray(['version' => 1, 'comment' => 'Tighter bands for Q4']);

    importAnswerKeyFiles('golden');
    reconcile();
    $exception = exceptionFor('TUP-S-000041');
    expect($exception->due_at?->diffInHours($exception->created_at, true))->toBe(48.0)
        ->and(ReconException::query()->where('severity', 'high')->where('amount_at_risk', '<', 500)->exists())->toBeTrue();
});

it('applies a changed approval threshold to new adjustments', function (): void {
    $this->actingAs(demoUser('admin@demo'))->put(route('settings.update', 'approvals'), ['approval_threshold' => '20.00', 'comment' => 'Pilot: all large write-offs to FM'])->assertSessionHas('success');
    importAnswerKeyFiles('golden');
    reconcile();

    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', exceptionFor('TUP-S-000041')), ['type' => 'write_off', 'amount' => '50.00', 'reason' => 'Agreed write-off']);

    expect(Adjustment::query()->sole()->high_value)->toBeTrue();
});

it('switches the AI assistant off from settings through the audited kill switch', function (): void {
    $this->actingAs(demoUser('admin@demo'))->put(route('settings.update', 'ai'), [
        'enabled' => false, 'model' => 'claude-opus-5', 'effort' => 'medium', 'owner_role' => 'Finance Manager', 'last_review_date' => '2026-09-01', 'comment' => 'Pause during vendor review',
    ])->assertSessionHas('success');

    expect(app(AiSettings::class)->killed())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'ai.kill_switch_toggled')->exists())->toBeTrue();
});
