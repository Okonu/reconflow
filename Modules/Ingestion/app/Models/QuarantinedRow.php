<?php

declare(strict_types=1);

namespace Modules\Ingestion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Ingestion\Enums\SourceType;

final class QuarantinedRow extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'source', 'business_date', 'row_number', 'record_key', 'reasons', 'raw'];

    protected $casts = [
        'source' => SourceType::class,
        'business_date' => 'immutable_date',
        'reasons' => 'array',
        'raw' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SourceBatch::class, 'batch_id');
    }
}
