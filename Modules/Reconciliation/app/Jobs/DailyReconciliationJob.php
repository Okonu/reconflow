<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Jobs;

use App\Support\BusinessCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Reconciliation\Actions\ReconcileDate;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Enums\RunTrigger;
use Modules\Reconciliation\Services\RuleConfigService;

final class DailyReconciliationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly ?string $businessDate = null,
        public readonly int $attempt = 1,
    ) {}

    public function uniqueId(): string
    {
        return ($this->businessDate ?? 'latest').':'.$this->attempt;
    }

    public function handle(ReconcileDate $reconcile, RuleConfigService $configs): void
    {
        $date = $this->businessDate ?? BusinessCalendar::latestClosedDate();
        $run = $reconcile->handle(new RunRequest($date, $this->attempt === 1 ? RunTrigger::Scheduled : RunTrigger::Retry, refreshFromSources: true), 'system:scheduler');

        $config = $configs->current();
        if ($run->status === RunStatus::BlockedData && $this->attempt < $config->blockedMaxAttempts) {
            self::dispatch($date, $this->attempt + 1)->delay(now()->addMinutes($config->blockedRetryMinutes));
        }
    }
}
