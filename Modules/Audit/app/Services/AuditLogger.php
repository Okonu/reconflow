<?php

declare(strict_types=1);

namespace Modules\Audit\Services;

use App\Contracts\AuditActor;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Models\AuditEvent;
use Modules\Audit\Support\AuditHasher;

final class AuditLogger
{
    public const SYSTEM_ACTOR = 'system';

    private const CHAIN_LOCK_KEY = 815001;

    public function record(
        BackedEnum|string $action,
        AuditActor|string|null $actor = null,
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $payload = [],
    ): AuditEvent {
        return DB::transaction(function () use ($action, $actor, $entityType, $entityId, $payload): AuditEvent {
            self::lockChain();
            $prevHash = DB::table('audit_events')->orderByDesc('id')->limit(1)->value('hash') ?? AuditEvent::GENESIS_HASH;

            $occurredAt = CarbonImmutable::now('UTC');
            $actorId = $actor instanceof AuditActor ? $actor->auditActorId() : null;
            $actorLabel = $actor instanceof AuditActor ? $actor->auditActorLabel() : ($actor ?? self::SYSTEM_ACTOR);
            $actionValue = $action instanceof BackedEnum ? (string) $action->value : $action;
            $entityIdValue = $entityId === null ? null : (string) $entityId;
            $requestId = Context::get('request_id');
            $normalised = AuditHasher::normalisePayload($payload);

            return AuditEvent::query()->create([
                'occurred_at' => $occurredAt,
                'actor_id' => $actorId,
                'actor_label' => $actorLabel,
                'action' => $actionValue,
                'entity_type' => $entityType,
                'entity_id' => $entityIdValue,
                'request_id' => $requestId,
                'payload' => $normalised,
                'prev_hash' => $prevHash,
                'hash' => AuditHasher::hash($prevHash, $occurredAt, $actorId, $actorLabel, $actionValue, $entityType, $entityIdValue, $requestId, $normalised),
            ]);
        });
    }

    public static function lockChain(): void
    {
        DB::select('select pg_advisory_xact_lock(?)', [self::CHAIN_LOCK_KEY]);
    }
}
