<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Actions;

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Models\RunSignoff;
use Modules\ExceptionManagement\Services\ExceptionWorkflow;
use Modules\ExceptionManagement\Services\SignoffService;
use Modules\Users\Models\User;

final class SignOffDate
{
    public function __construct(
        private readonly SignoffService $signoffs,
        private readonly ExceptionWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, string $date, string $comment): RunSignoff
    {
        return DB::transaction(function () use ($user, $date, $comment): RunSignoff {
            DB::select('select pg_advisory_xact_lock(?)', [crc32('recon-run:'.$date)]);
            $status = $this->signoffs->status($date);
            if (! $status['can_sign']) {
                throw DomainException::conflict('This date cannot be signed off yet: '.implode(' ', $status['blockers']));
            }
            $carried = $status['to_acknowledge'];
            if ($carried->isNotEmpty() && trim($comment) === '') {
                throw DomainException::invalid('Add a comment acknowledging the open exceptions that will be carried forward.');
            }

            $signoff = RunSignoff::query()->create([
                'business_date' => $date,
                'run_id' => $status['run']->id,
                'signed_by' => $user->id,
                'comment' => $comment,
                'carried_exception_ids' => $carried->pluck('id')->all(),
                'signed_at' => now(),
            ]);
            foreach ($carried as $exception) {
                assert($exception instanceof ReconException);
                $this->workflow->event($exception, $user, 'carried_forward', $comment, ['signoff_id' => $signoff->id]);
            }
            $this->audit->record(ExceptionAuditAction::SignedOff, $user, 'recon_run', $status['run']->id, [
                'business_date' => $date,
                'run_version' => $status['run']->version,
                'comment' => $comment,
                'carried_exception_ids' => $carried->pluck('id')->all(),
            ]);

            return $signoff;
        });
    }
}
