<?php

declare(strict_types=1);

namespace Modules\Audit\Actions;

use App\Contracts\AuditActor;
use Modules\Audit\DTOs\ChainVerification;
use Modules\Audit\Enums\AuditAction;
use Modules\Audit\Services\AuditLogger;
use Modules\Audit\Services\ChainVerifier;

final class VerifyAuditChain
{
    public function __construct(
        private readonly ChainVerifier $verifier,
        private readonly AuditLogger $logger,
    ) {}

    public function handle(AuditActor $actor): ChainVerification
    {
        $result = $this->verifier->verify();

        $this->logger->record(AuditAction::ChainVerified, $actor, 'audit_events', payload: [
            'ok' => $result->ok,
            'events_checked' => $result->eventsChecked,
            'broken_at_id' => $result->brokenAtId,
            'head_hash' => $result->headHash,
        ]);

        return $result;
    }
}
