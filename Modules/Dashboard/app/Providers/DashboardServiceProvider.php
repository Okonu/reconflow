<?php

declare(strict_types=1);

namespace Modules\Dashboard\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Modules\Dashboard\Enums\DashboardPermission;

final class DashboardServiceProvider extends ModuleProvider
{
    protected string $name = 'Dashboard';

    protected string $nameLower = 'dashboard';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(DashboardPermission::class);
    }
}
