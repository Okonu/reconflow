<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BusinessDateLock;
use App\Support\Authorization\PermissionRegistry;
use App\Support\NoDateLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class);
        $this->app->bindIf(BusinessDateLock::class, NoDateLock::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);

        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        TrustProxies::at(array_filter(array_map('trim', explode(',', (string) config('reconflow.trusted_proxies')))));
    }
}
