<?php

declare(strict_types=1);

namespace Modules\Ingestion\Providers;

use App\Contracts\PersonalDataStore;
use App\Http\Controllers\System\MetricsController;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Ingestion\Actions\ResetDemoData;
use Modules\Ingestion\Connectors\LocalMockSourceConnector;
use Modules\Ingestion\Connectors\MockSourceApiConnector;
use Modules\Ingestion\Connectors\SourceConnector;
use Modules\Ingestion\Console\Commands\BootstrapDemoCommand;
use Modules\Ingestion\Console\Commands\PurgeStagedUploadsCommand;
use Modules\Ingestion\Console\Commands\SeedCommand;
use Modules\Ingestion\Enums\IngestionPermission;
use Modules\Ingestion\Metrics\IngestionMetrics;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Policies\SourceBatchPolicy;
use Modules\Ingestion\Policies\UploadStagingPolicy;
use Modules\Ingestion\Support\Demo\IngestionTables;
use Modules\Ingestion\Support\IngestionPersonalData;

final class IngestionServiceProvider extends ModuleProvider
{
    protected string $name = 'Ingestion';

    protected string $nameLower = 'ingestion';

    protected array $commands = [
        SeedCommand::class,
        PurgeStagedUploadsCommand::class,
        BootstrapDemoCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(SourceConnector::class, fn ($app) => config('ingestion.sources.driver') === 'http'
            ? $app->make(MockSourceApiConnector::class)
            : $app->make(LocalMockSourceConnector::class));
        $this->app->tag([IngestionMetrics::class], MetricsController::COLLECTOR_TAG);
        $this->app->tag([IngestionTables::class], ResetDemoData::RESETTER_TAG);
        $this->app->tag([IngestionPersonalData::class], PersonalDataStore::TAG);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(IngestionPermission::class);
        Gate::policy(UploadStaging::class, UploadStagingPolicy::class);
        Gate::policy(SourceBatch::class, SourceBatchPolicy::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(PurgeStagedUploadsCommand::class)
            ->cron((string) config('ingestion.staging_purge_schedule'))
            ->onOneServer()
            ->withoutOverlapping();
    }
}
