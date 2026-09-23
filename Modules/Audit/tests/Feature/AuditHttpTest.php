<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Audit\Models\AuditEvent;

it('shows the audit log to users with audit.view', function (): void {
    $this->actingAs(demoUser('auditor@demo'))
        ->get(route('audit.index', ['action' => 'user.']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audit/Index')
            ->has('events.data')
            ->where('filters.action', 'user.')
            ->where('can.verify', true));
});

it('verifies the chain on request and audits the verification', function (): void {
    $response = $this->actingAs(demoUser('auditor@demo'))->postJson(route('audit.verify'));

    $response->assertOk()->assertJson(['ok' => true, 'anchor' => 'genesis']);
    expect(AuditEvent::query()->where('action', 'audit.verified')->exists())->toBeTrue();
});
