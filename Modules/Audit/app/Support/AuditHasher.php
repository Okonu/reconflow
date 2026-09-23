<?php

declare(strict_types=1);

namespace Modules\Audit\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Audit\DTOs\AuditRecord;
use Modules\DataProtection\Support\PersonalData;

final class AuditHasher
{
    public static function normalisePayload(array $payload): array
    {
        $json = json_encode(PersonalData::scrub($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return (array) json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    public static function timestamp(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->utc()->format('Y-m-d\TH:i:s.uP');
    }

    public static function hash(string $prevHash, DateTimeInterface $occurredAt, ?int $actorId, string $actorLabel, string $action, ?string $entityType, ?string $entityId, ?string $requestId, array $payload): string
    {
        $content = self::canonicalJson([
            'occurred_at' => self::timestamp($occurredAt),
            'actor_id' => $actorId,
            'actor_label' => $actorLabel,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_id' => $requestId,
            'payload' => $payload,
        ]);

        return hash('sha256', $prevHash.$content);
    }

    public static function recompute(AuditRecord $record): string
    {
        return self::hash(
            $record->prevHash,
            $record->occurredAt,
            $record->actorId,
            $record->actorLabel,
            $record->action,
            $record->entityType,
            $record->entityId,
            $record->requestId,
            $record->payload,
        );
    }

    private static function canonicalJson(array $data): string
    {
        return json_encode(self::sortKeys($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $sorted = array_map(self::sortKeys(...), $value);
        if (! array_is_list($sorted)) {
            ksort($sorted, SORT_STRING);
        }

        return $sorted;
    }
}
