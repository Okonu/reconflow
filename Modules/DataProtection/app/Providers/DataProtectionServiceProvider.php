<?php

declare(strict_types=1);

namespace Modules\DataProtection\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Modules\DataProtection\Enums\DataProtectionPermission;
use Modules\DataProtection\Services\Pseudonymiser;

final class DataProtectionServiceProvider extends ModuleProvider
{
    protected string $name = 'DataProtection';

    protected string $nameLower = 'dataprotection';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Pseudonymiser::class, fn (): Pseudonymiser => new Pseudonymiser((string) config('dataprotection.pii_hash_salt')));
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(DataProtectionPermission::class);
    }
}
