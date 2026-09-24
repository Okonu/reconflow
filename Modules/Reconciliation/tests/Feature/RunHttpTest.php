<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Reconciliation\Models\ReconRun;

it('shows run history with the latest closed date preselected and its source readiness', function (): void {
    importAnswerKeyFiles('golden');

    $this->get(route('runs.index', ['date' => '2026-09-22']))->assertInertia(fn (Assert $page) => $page
        ->component('Reconciliation/Runs/Index')
        ->where('readiness.date', '2026-09-22')
        ->where('readiness.closed', true)
        ->where('readiness.sources.0.manual', true)
        ->where('can.trigger', true));
});

it('runs now for a date and redirects to the run detail', function (): void {
    importAnswerKeyFiles('golden');

    $response = $this->post(route('runs.store'), ['business_date' => '2026-09-22']);
    $run = ReconRun::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('runs.show', $run));
    expect($run->status->value)->toBe('completed')->and($run->summary['items'])->toBe(65);

    $this->get(route('runs.show', $run))->assertInertia(fn (Assert $page) => $page
        ->component('Reconciliation/Runs/Show')
        ->where('run.data.status', 'completed')
        ->where('run.data.batches.sales.manual', true)
        ->has('run.data.summary.by_status'));
});

it('refuses future dates and lets auditors watch but not run', function (): void {
    $this->actingAs(demoUser('analyst@demo'))->post(route('runs.store'), ['business_date' => now()->addDays(2)->toDateString()])->assertSessionHasErrors('business_date');
    $this->actingAs(demoUser('auditor@demo'))->post(route('runs.store'), ['business_date' => '2026-09-22'])->assertForbidden();
    $this->actingAs(demoUser('auditor@demo'))->get(route('runs.index'))->assertInertia(fn (Assert $page) => $page->where('can.trigger', false));
});
