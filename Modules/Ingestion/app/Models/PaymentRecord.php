<?php

declare(strict_types=1);

namespace Modules\Ingestion\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentRecord extends Model
{
    public $timestamps = false;

    protected $table = 'payment_records';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => MoneyCast::class,
        'business_date' => 'immutable_date',
        'paid_at' => 'immutable_datetime',
        'payment_date' => 'immutable_date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SourceBatch::class, 'batch_id');
    }
}
