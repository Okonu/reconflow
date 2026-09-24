<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

final class RuleConfigVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'recon_rule_configs';

    protected $fillable = ['version', 'values', 'comment', 'created_by'];

    protected $casts = ['values' => 'array'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
