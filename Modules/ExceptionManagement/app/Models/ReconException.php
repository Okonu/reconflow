<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ExceptionManagement\DTOs\QueueFilters;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Enums\Severity;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Users\Models\User;

final class ReconException extends Model
{
    protected $table = 'exceptions';

    protected $fillable = [
        'key', 'identity', 'business_date', 'result_id', 'run_id', 'section', 'status', 'family', 'category', 'severity',
        'amount_at_risk', 'transaction_id', 'payment_ids', 'region', 'owner_id', 'due_at', 'state', 'needs_review', 'soft',
        'predecessor_id', 'resolution', 'escalated_at', 'resolved_at',
    ];

    protected $casts = [
        'business_date' => 'immutable_date',
        'status' => ReconStatus::class,
        'severity' => Severity::class,
        'state' => ExceptionState::class,
        'amount_at_risk' => MoneyCast::class,
        'payment_ids' => 'array',
        'needs_review' => 'boolean',
        'soft' => 'boolean',
        'due_at' => 'immutable_datetime',
        'escalated_at' => 'immutable_datetime',
        'resolved_at' => 'immutable_datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(ReconResult::class, 'result_id');
    }

    public function resultRecord(): ?ReconResult
    {
        $result = $this->result;

        return $result instanceof ReconResult ? $result : null;
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExceptionEvent::class, 'exception_id')->orderBy('id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && ! $this->state->isClosed() && $this->due_at->isPast();
    }

    public static function openStateValues(): array
    {
        return array_map(fn (ExceptionState $s) => $s->value, ExceptionState::openStates());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', self::openStateValues());
    }

    public function scopeQueue(Builder $query, QueueFilters $filters, int $viewerId): Builder
    {
        return $query
            ->with('owner')
            ->when($filters->state === 'open', fn (Builder $q) => $q->whereIn('state', self::openStateValues()))
            ->when($filters->state !== null && $filters->state !== 'open' && $filters->state !== 'all', fn (Builder $q) => $q->where('state', $filters->state))
            ->when($filters->category, fn (Builder $q, string $v) => $q->where('category', $v))
            ->when($filters->severity, fn (Builder $q, string $v) => $q->where('severity', $v))
            ->when($filters->owner === 'me', fn (Builder $q) => $q->where('owner_id', $viewerId))
            ->when($filters->owner === 'unassigned', fn (Builder $q) => $q->whereNull('owner_id'))
            ->when(is_numeric($filters->owner), fn (Builder $q) => $q->where('owner_id', (int) $filters->owner))
            ->when($filters->overdue, fn (Builder $q) => $q->whereIn('state', self::openStateValues())->whereNotNull('due_at')->where('due_at', '<', now()))
            ->when($filters->businessDate, fn (Builder $q, string $d) => $q->whereDate('business_date', $d))
            ->when($filters->search, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w->where('transaction_id', 'ilike', "%{$s}%")->orWhereRaw('payment_ids::text ilike ?', ["%{$s}%"])))
            ->orderByRaw("case severity when 'critical' then 4 when 'high' then 3 when 'medium' then 2 else 1 end desc")
            ->orderBy('due_at')
            ->orderByDesc('business_date');
    }
}
