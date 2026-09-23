<?php

declare(strict_types=1);

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Audit\DTOs\AuditEventFilters;

final class AuditEvent extends Model
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected $table = 'audit_events';

    protected $fillable = [
        'occurred_at', 'actor_id', 'actor_label', 'action', 'entity_type', 'entity_id',
        'request_id', 'payload', 'prev_hash', 'hash',
    ];

    protected $casts = [
        'occurred_at' => 'immutable_datetime',
        'payload' => 'array',
        'actor_id' => 'integer',
    ];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Audit events are append-only'));
        self::deleting(fn (): never => throw new LogicException('Audit events are append-only'));
    }

    public function scopeFiltered(Builder $query, AuditEventFilters $filters): Builder
    {
        return $query
            ->when($filters->action, fn (Builder $q, string $v) => $q->where('action', 'ilike', $v.'%'))
            ->when($filters->entityType, fn (Builder $q, string $v) => $q->where('entity_type', $v))
            ->when($filters->entityId, fn (Builder $q, string $v) => $q->where('entity_id', $v))
            ->when($filters->actor, fn (Builder $q, string $v) => $q->where('actor_label', 'ilike', '%'.$v.'%'))
            ->when($filters->from, fn (Builder $q, $v) => $q->where('occurred_at', '>=', $v))
            ->when($filters->to, fn (Builder $q, $v) => $q->where('occurred_at', '<', $v));
    }
}
