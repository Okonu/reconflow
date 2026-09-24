<?php

declare(strict_types=1);

namespace Modules\Notifications\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\BusinessCalendar;
use App\Support\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Modules\ExceptionManagement\Events\ExceptionsSynced;
use Modules\Notifications\Console\Commands\SendDailySummaryCommand;
use Modules\Notifications\Enums\NotificationPermission;
use Modules\Notifications\Listeners\NotifyCriticalExceptions;
use Modules\Notifications\Listeners\NotifyRunProblems;
use Modules\Reconciliation\Events\RunFinished;
use Modules\Users\Models\User;

final class NotificationsServiceProvider extends ModuleProvider
{
    protected string $name = 'Notifications';

    protected string $nameLower = 'notifications';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        SendDailySummaryCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(NotificationPermission::class);
        Event::listen(RunFinished::class, NotifyRunProblems::class);
        Event::listen(ExceptionsSynced::class, NotifyCriticalExceptions::class);
        Inertia::share('notifications', fn (Request $request): array => [
            'unread' => $request->user() instanceof User ? $request->user()->unreadNotifications()->count() : 0,
        ]);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(SendDailySummaryCommand::class)
            ->dailyAt((string) config('notifications.daily_summary_time'))
            ->timezone(BusinessCalendar::timezone())
            ->onOneServer();
    }
}
