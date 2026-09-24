<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

final class RunSignoff extends Model
{
    public $timestamps = false;

    protected $table = 'run_signoffs';

    protected $fillable = ['business_date', 'run_id', 'signed_by', 'comment', 'carried_exception_ids', 'signed_at', 'reopened_by', 'reopen_reason', 'reopened_at'];

    protected $casts = [
        'business_date' => 'immutable_date',
        'carried_exception_ids' => 'array',
        'signed_at' => 'immutable_datetime',
        'reopened_at' => 'immutable_datetime',
    ];

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function scopeActiveFor(Builder $query, string $date): Builder
    {
        return $query->whereDate('business_date', $date)->whereNull('reopened_at');
    }
}
