<?php

declare(strict_types=1);

it('shows KPIs, trend, categories, ageing and the latest run for a date', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();

    $this->actingAs(demoUser('analyst@demo'))->get(route('home', ['date' => '2026-09-22']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard/Index')
            ->where('dashboard.date', '2026-09-22')
            ->where('dashboard.kpis.items', 65)
            ->where('dashboard.kpis.open_exceptions', 20)
            ->where('dashboard.kpis.timing_items', 2)
            ->where('dashboard.latest_run.status', 'completed')
            ->where('dashboard.latest_run.signed_off', false)
            ->has('dashboard.trend', 14)
            ->has('dashboard.ageing', 4)
            ->where('dashboard.kpis.minutes_saved', fn ($m) => is_int($m) && $m > 0));
});

it('shows a welcome page to users without the dashboard permission', function (): void {
    $this->actingAs(userWithPermissions([]))->get(route('home'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard/Index')->where('dashboard', null));
});
