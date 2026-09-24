<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Modules\ExceptionManagement\Enums\Severity;
use Modules\ExceptionManagement\Events\ExceptionsSynced;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Notifications\Enums\AlertLevel;
use Modules\Notifications\Enums\NotificationPermission;
use Modules\Notifications\Notifications\Alert;
use Modules\Notifications\Services\Notifier;

final class NotifyCriticalExceptions
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(ExceptionsSynced $event): void
    {
        if ($event->openedIds === []) {
            return;
        }
        $critical = ReconException::query()->whereIn('id', $event->openedIds)->where('severity', Severity::Critical->value)->get(['id', 'amount_at_risk']);
        if ($critical->isEmpty()) {
            return;
        }
        $total = $critical->reduce(fn (BigDecimal $sum, ReconException $e): BigDecimal => $sum->plus(Money::of((string) $e->amount_at_risk)), Money::zero());
        $date = $event->run->business_date->toDateString();
        $count = $critical->count();

        $this->notifier->send(NotificationPermission::CriticalAlerts, new Alert(
            'critical_exceptions',
            $count === 1 ? "1 critical exception opened for {$date}" : "{$count} critical exceptions opened for {$date}",
            'Value at risk: $'.number_format((float) (string) $total, 2).'. Critical exceptions block sign-off and are due within 8 hours.',
            route('exceptions.index', ['business_date' => $date, 'severity' => Severity::Critical->value]),
            AlertLevel::Critical,
        ));
    }
}
