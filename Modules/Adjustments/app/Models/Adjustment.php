<?php

declare(strict_types=1);

namespace Modules\Adjustments\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Enums\AdjustmentType;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Users\Models\User;

final class Adjustment extends Model
{
    protected $fillable = [
        'exception_id', 'type', 'amount', 'currency', 'reason', 'proposed_by', 'decided_by', 'decided_at', 'decision_comment',
        'state', 'high_value', 'idempotency_key', 'journal', 'erp_journal_id', 'posted_at', 'posting_attempts',
    ];

    protected $casts = [
        'type' => AdjustmentType::class,
        'state' => AdjustmentState::class,
        'amount' => MoneyCast::class,
        'high_value' => 'boolean',
        'journal' => 'array',
        'decided_at' => 'immutable_datetime',
        'posted_at' => 'immutable_datetime',
    ];

    public function exception(): BelongsTo
    {
        return $this->belongsTo(ReconException::class, 'exception_id');
    }

    public function exceptionRecord(): ReconException
    {
        $exception = $this->exception;

        return $exception instanceof ReconException ? $exception : ReconException::query()->findOrFail($this->exception_id);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(ErpPostingOut::class, 'adjustment_id')->orderBy('id');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('state', AdjustmentState::PendingApproval->value);
    }
}
