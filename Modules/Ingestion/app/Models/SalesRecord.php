<?php

declare(strict_types=1);

namespace Modules\Ingestion\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesRecord extends Model
{
    public $timestamps = false;

    protected $table = 'sales_records';

    protected $guarded = ['id'];

    protected $casts = [
        'expected_amount' => MoneyCast::class,
        'business_date' => 'immutable_date',
        'sold_at' => 'immutable_datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SourceBatch::class, 'batch_id');
    }
}
