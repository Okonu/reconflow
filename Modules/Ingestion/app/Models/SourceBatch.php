<?php

declare(strict_types=1);

namespace Modules\Ingestion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ingestion\Enums\BatchMode;
use Modules\Ingestion\Enums\BatchOrigin;
use Modules\Ingestion\Enums\BatchStatus;
use Modules\Ingestion\Enums\SourceType;
use Modules\Users\Models\User;

final class SourceBatch extends Model
{
    protected $fillable = [
        'source', 'business_date', 'version', 'parent_batch_id', 'origin', 'status', 'mode', 'manual', 'filename', 'checksum',
        'rows_received', 'rows_loaded', 'rows_added', 'rows_quarantined', 'dq_summary', 'extracted_at', 'created_by', 'superseded_by_id',
    ];

    protected $casts = [
        'source' => SourceType::class,
        'origin' => BatchOrigin::class,
        'status' => BatchStatus::class,
        'mode' => BatchMode::class,
        'manual' => 'boolean',
        'business_date' => 'immutable_date',
        'extracted_at' => 'immutable_datetime',
        'dq_summary' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quarantinedRows(): HasMany
    {
        return $this->hasMany(QuarantinedRow::class, 'batch_id')->orderBy('row_number');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BatchStatus::Active->value);
    }

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['business_date'] ?? null, fn (Builder $q, string $d) => $q->whereDate('business_date', $d))
            ->when($filters['source'] ?? null, fn (Builder $q, string $s) => $q->where('source', $s))
            ->when(! ($filters['include_superseded'] ?? false), fn (Builder $q) => $q->where('status', BatchStatus::Active->value))
            ->orderByDesc('business_date')->orderBy('source')->orderByDesc('version');
    }

    public function scopeFor(Builder $query, SourceType $source, string $date): Builder
    {
        return $query->where('source', $source->value)->whereDate('business_date', $date);
    }
}
