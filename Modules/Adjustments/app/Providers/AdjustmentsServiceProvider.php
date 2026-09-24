<?php

declare(strict_types=1);

namespace Modules\Adjustments\Providers;

use App\Contracts\ExceptionDetailContributor;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustments\Contracts\ErpClient;
use Modules\Adjustments\Enums\AdjustmentPermission;
use Modules\Adjustments\Models\Adjustment;
use Modules\Adjustments\Policies\AdjustmentPolicy;
use Modules\Adjustments\Services\HttpMockErpClient;
use Modules\Adjustments\Services\LocalMockErpClient;
use Modules\Adjustments\Support\AdjustmentsContribution;
use Modules\Adjustments\Support\Demo\AdjustmentTables;
use Modules\Ingestion\Actions\ResetDemoData;

final class AdjustmentsServiceProvider extends ModuleProvider
{
    protected string $name = 'Adjustments';

    protected string $nameLower = 'adjustments';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(ErpClient::class, fn ($app) => config('adjustments.erp.driver') === 'http'
            ? $app->make(HttpMockErpClient::class)
            : $app->make(LocalMockErpClient::class));
        $this->app->tag([AdjustmentsContribution::class], ExceptionDetailContributor::TAG);
        $this->app->tag([AdjustmentTables::class], ResetDemoData::RESETTER_TAG);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(AdjustmentPermission::class);
        Gate::policy(Adjustment::class, AdjustmentPolicy::class);
    }
}
