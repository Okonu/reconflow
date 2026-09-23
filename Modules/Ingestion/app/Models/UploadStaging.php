<?php

declare(strict_types=1);

namespace Modules\Ingestion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Ingestion\Enums\ImportMode;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Enums\StagingState;
use Modules\Users\Models\User;

final class UploadStaging extends Model
{
    use HasUuids;

    protected $table = 'upload_staging';

    protected $fillable = [
        'source', 'business_date', 'filename', 'extension', 'size_bytes', 'checksum', 'uploaded_by', 'header_check',
        'rows', 'rows_read', 'rows_valid', 'rows_invalid', 'duplicate_of_batch_id', 'date_has_data', 'state', 'mode',
        'batch_id', 'expires_at',
    ];

    protected $casts = [
        'source' => SourceType::class,
        'state' => StagingState::class,
        'mode' => ImportMode::class,
        'business_date' => 'immutable_date',
        'expires_at' => 'immutable_datetime',
        'header_check' => 'array',
        'rows' => 'array',
        'date_has_data' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SourceBatch::class, 'batch_id');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('state', StagingState::Staged->value)->where('expires_at', '<', now());
    }

    public function scopeHistory(Builder $query, int $limit = 50): Builder
    {
        return $query->with('uploader')->latest()->limit($limit);
    }

    public function isBlocked(): bool
    {
        return ! ($this->header_check['ok'] ?? false);
    }
}
