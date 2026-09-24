<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Providers;

use App\Contracts\BusinessDateLock;
use App\Http\Controllers\System\MetricsController;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\ExceptionManagement\Enums\ExceptionPermission;
use Modules\ExceptionManagement\Listeners\ApplyItemStateChange;
use Modules\ExceptionManagement\Listeners\SplitRejectedFuzzyMatch;
use Modules\ExceptionManagement\Listeners\SyncExceptionsAfterRun;
use Modules\ExceptionManagement\Metrics\ExceptionMetrics;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Models\RunSignoff;
use Modules\ExceptionManagement\Policies\ExceptionPolicy;
use Modules\ExceptionManagement\Policies\RunSignoffPolicy;
use Modules\ExceptionManagement\Support\Demo\ExceptionTables;
use Modules\ExceptionManagement\Support\SignoffDateLock;
use Modules\Ingestion\Actions\ResetDemoData;
use Modules\Reconciliation\Events\FuzzyMatchRejected;
use Modules\Reconciliation\Events\ItemStateChanged;
use Modules\Reconciliation\Events\RunFinished;

final class ExceptionManagementServiceProvider extends ModuleProvider
{
    protected string $name = 'ExceptionManagement';

    protected string $nameLower = 'exceptionmanagement';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(BusinessDateLock::class, SignoffDateLock::class);
        $this->app->tag([ExceptionMetrics::class], MetricsController::COLLECTOR_TAG);
        $this->app->tag([ExceptionTables::class], ResetDemoData::RESETTER_TAG);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(ExceptionPermission::class);
        Gate::policy(ReconException::class, ExceptionPolicy::class);
        Gate::policy(RunSignoff::class, RunSignoffPolicy::class);
        Event::listen(RunFinished::class, SyncExceptionsAfterRun::class);
        Event::listen(ItemStateChanged::class, ApplyItemStateChange::class);
        Event::listen(FuzzyMatchRejected::class, SplitRejectedFuzzyMatch::class);
    }
}
