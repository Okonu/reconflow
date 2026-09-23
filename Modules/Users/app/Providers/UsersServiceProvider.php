<?php

declare(strict_types=1);

namespace Modules\Users\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\Users\Enums\UserPermission;
use Modules\Users\Http\Middleware\EnsureUserIsActive;
use Modules\Users\Http\Resources\AuthenticatedUserResource;
use Modules\Users\Models\User;
use Modules\Users\Policies\UserPolicy;

final class UsersServiceProvider extends ModuleProvider
{
    protected string $name = 'Users';

    protected string $nameLower = 'users';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $registry = $this->app->make(PermissionRegistry::class);
        $registry->register(UserPermission::class);
        $registry->protect(UserPermission::Manage);

        Gate::policy(User::class, UserPolicy::class);
        $kernel = $this->app->make(Kernel::class);
        if ($kernel instanceof HttpKernel) {
            $kernel->appendMiddlewareToGroup('web', EnsureUserIsActive::class);
        }

        Inertia::share('auth', function (Request $request): array {
            $user = $request->user();

            return $user instanceof User
                ? (new AuthenticatedUserResource($user->loadMissing('roles')))->resolve($request)
                : ['user' => null, 'permissions' => []];
        });
    }
}
