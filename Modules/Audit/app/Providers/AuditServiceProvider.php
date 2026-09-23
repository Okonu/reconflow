<?php

declare(strict_types=1);

namespace Modules\Audit\Providers;

use App\Http\Controllers\System\MetricsController;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Console\Commands\ArchiveAuditEventsCommand;
use Modules\Audit\Enums\AuditPermission;
use Modules\Audit\Metrics\AuditMetrics;
use Modules\Audit\Models\AuditEvent;
use Modules\Audit\Policies\AuditEventPolicy;
use Modules\Audit\Services\AuditLogger;

final class AuditServiceProvider extends ModuleProvider
{
    protected string $name = 'Audit';

    protected string $nameLower = 'audit';

    protected array $commands = [
        ArchiveAuditEventsCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AuditLogger::class);
        $this->app->tag([AuditMetrics::class], MetricsController::COLLECTOR_TAG);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(AuditPermission::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(ArchiveAuditEventsCommand::class)
            ->cron((string) config('audit.archive_schedule'))
            ->timezone((string) config('reconflow.display_timezone'))
            ->onOneServer()
            ->withoutOverlapping();
    }
}
