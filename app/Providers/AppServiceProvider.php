<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BusinessDateLock;
use App\Policies\SettingsPolicy;
use App\Support\Authorization\PermissionRegistry;
use App\Support\NoDateLock;
use App\Support\Settings\VersionedSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class);
        $this->app->scoped(VersionedSettings::class);
        $this->app->bindIf(BusinessDateLock::class, NoDateLock::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);

        Gate::define('viewSettings', [SettingsPolicy::class, 'view']);
        Gate::define('manageSettings', [SettingsPolicy::class, 'manage']);

        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        TrustProxies::at(array_filter(array_map('trim', explode(',', (string) config('reconflow.trusted_proxies')))));
    }
}
