<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

final class ManualMatch extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'recon_manual_matches';

    protected $fillable = [
        'transaction_id', 'sale_date', 'sale_record_id', 'sale_result_id', 'payment_identity', 'payment_id',
        'payment_date', 'payment_result_id', 'confirmed_by', 'reason',
    ];

    protected $casts = [
        'sale_date' => 'immutable_date',
        'payment_date' => 'immutable_date',
    ];

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
