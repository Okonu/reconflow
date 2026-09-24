<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Enums\RunTrigger;
use Modules\Users\Models\User;

final class ReconRun extends Model
{
    protected $table = 'recon_runs';

    protected $fillable = [
        'business_date', 'version', 'status', 'trigger', 'triggered_by', 'provisional', 'stale_at', 'rule_config_id',
        'rule_config', 'batches', 'summary', 'blocked_reason', 'error', 'superseded_by_id', 'started_at', 'finished_at', 'duration_ms',
    ];

    protected $casts = [
        'business_date' => 'immutable_date',
        'status' => RunStatus::class,
        'trigger' => RunTrigger::class,
        'provisional' => 'boolean',
        'stale_at' => 'immutable_datetime',
        'rule_config' => 'array',
        'batches' => 'array',
        'summary' => 'array',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(ReconResult::class, 'run_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('business_date', $date);
    }

    public function scopeHistory(Builder $query): Builder
    {
        return $query->with('triggeredBy')->orderByDesc('business_date')->orderByDesc('version');
    }

    public function scopeLatestCompleted(Builder $query): Builder
    {
        return $query->where('status', RunStatus::Completed->value)->whereNull('superseded_by_id');
    }
}
