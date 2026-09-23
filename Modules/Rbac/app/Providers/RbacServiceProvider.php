<?php

declare(strict_types=1);

namespace Modules\Rbac\Providers;

use App\Contracts\RoleAssignable;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Rbac\Enums\RbacPermission;
use Modules\Rbac\Models\Role;
use Modules\Rbac\Policies\RolePolicy;

final class RbacServiceProvider extends ModuleProvider
{
    protected string $name = 'Rbac';

    protected string $nameLower = 'rbac';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $registry = $this->app->make(PermissionRegistry::class);
        $registry->register(RbacPermission::class);
        $registry->protect(RbacPermission::ManageRoles);

        Gate::policy(Role::class, RolePolicy::class);
        Route::bind('assignee', fn (string $id): RoleAssignable => config('auth.providers.users.model')::query()->findOrFail((int) $id));
    }
}
