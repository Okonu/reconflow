<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Actions;

use App\Exceptions\DomainException;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Models\RunSignoff;
use Modules\Users\Models\User;

final class ReopenDate
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, string $date, string $reason): RunSignoff
    {
        $signoff = RunSignoff::query()->activeFor($date)->first() ?? throw DomainException::conflict('This date is not signed off.');
        $signoff->update(['reopened_by' => $user->id, 'reopen_reason' => $reason, 'reopened_at' => now()]);
        $this->audit->record(ExceptionAuditAction::Reopened, $user, 'recon_run', $signoff->run_id, [
            'business_date' => $date,
            'signoff_id' => $signoff->id,
            'reason' => $reason,
        ]);

        return $signoff;
    }
}
