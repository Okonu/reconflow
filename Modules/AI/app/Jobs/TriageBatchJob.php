<?php

declare(strict_types=1);

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AI\Services\AiSettings;
use Modules\AI\Services\AiUnavailable;
use Modules\AI\Services\TriageService;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Users\Models\User;

final class TriageBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $userId, public readonly array $exceptionIds) {}

    public function handle(TriageService $triage, AiSettings $settings): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null) {
            return;
        }
        foreach (array_chunk($this->exceptionIds, 50) as $chunk) {
            foreach (ReconException::query()->whereKey($chunk)->get() as $exception) {
                if (! $settings->enabled()) {
                    return;
                }
                try {
                    $triage->suggest($user, $exception);
                } catch (AiUnavailable) {
                    continue;
                }
            }
        }
    }
}
