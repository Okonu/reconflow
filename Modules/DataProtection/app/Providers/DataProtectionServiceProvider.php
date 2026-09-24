<?php

declare(strict_types=1);

namespace Modules\DataProtection\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\DataProtection\Console\Commands\AnonymiseExpiredCommand;
use Modules\DataProtection\Enums\DataProtectionPermission;
use Modules\DataProtection\Policies\PersonalDataPolicy;
use Modules\DataProtection\Services\Pseudonymiser;

final class DataProtectionServiceProvider extends ModuleProvider
{
    protected string $name = 'DataProtection';

    protected string $nameLower = 'dataprotection';

    protected array $commands = [
        AnonymiseExpiredCommand::class,
    ];

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

        Gate::define('unmaskPersonalData', [PersonalDataPolicy::class, 'unmask']);
        Gate::define('erasePersonalData', [PersonalDataPolicy::class, 'erase']);

        $this->app->make(PermissionRegistry::class)->register(DataProtectionPermission::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(AnonymiseExpiredCommand::class)->dailyAt('02:30')->onOneServer();
    }
}
