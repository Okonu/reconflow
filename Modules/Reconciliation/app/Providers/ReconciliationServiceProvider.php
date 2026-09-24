<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Providers;

use App\Contracts\SettingsSection;
use App\Http\Controllers\System\MetricsController;
use App\Support\Authorization\PermissionRegistry;
use App\Support\BusinessCalendar;
use App\Support\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Ingestion\Actions\ResetDemoData;
use Modules\Ingestion\Events\DemoDataSeeded;
use Modules\Reconciliation\Console\Commands\DailyReconciliationCommand;
use Modules\Reconciliation\Console\Commands\ReconcileCommand;
use Modules\Reconciliation\Enums\ReconPermission;
use Modules\Reconciliation\Listeners\ReconcileSeededDates;
use Modules\Reconciliation\Metrics\ReconciliationMetrics;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Policies\ReconResultPolicy;
use Modules\Reconciliation\Policies\ReconRunPolicy;
use Modules\Reconciliation\Services\RuleConfigService;
use Modules\Reconciliation\Support\Demo\ReconciliationTables;
use Modules\Reconciliation\Support\RuleSettingsSection;
use Throwable;

final class ReconciliationServiceProvider extends ModuleProvider
{
    protected string $name = 'Reconciliation';

    protected string $nameLower = 'reconciliation';

    protected array $commands = [
        ReconcileCommand::class,
        DailyReconciliationCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->tag([ReconciliationMetrics::class], MetricsController::COLLECTOR_TAG);
        $this->app->tag([ReconciliationTables::class], ResetDemoData::RESETTER_TAG);
        $this->app->tag([RuleSettingsSection::class], SettingsSection::TAG);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(ReconPermission::class);
        Gate::policy(ReconRun::class, ReconRunPolicy::class);
        Gate::policy(ReconResult::class, ReconResultPolicy::class);
        Event::listen(DemoDataSeeded::class, ReconcileSeededDates::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        try {
            $time = $this->app->make(RuleConfigService::class)->current()->scheduleTime;
        } catch (Throwable) {
            $time = (string) config('reconciliation.defaults.schedule_time');
        }

        $schedule->command(DailyReconciliationCommand::class)
            ->dailyAt($time)
            ->timezone(BusinessCalendar::timezone())
            ->onOneServer();
    }
}
