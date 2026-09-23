<?php

declare(strict_types=1);

namespace Modules\Ingestion\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Ingestion\Enums\SourceType;

final class MockSourceRow extends Model
{
    public $timestamps = false;

    protected $fillable = ['source', 'record_date', 'occurred_at', 'sequence', 'payload'];

    protected $casts = [
        'source' => SourceType::class,
        'record_date' => 'immutable_date',
        'occurred_at' => 'immutable_datetime',
        'payload' => 'array',
    ];
}
