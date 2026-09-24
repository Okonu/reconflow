<?php

declare(strict_types=1);

namespace Modules\Adjustments\Models;

use Illuminate\Database\Eloquent\Model;

final class ErpPostingOut extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'erp_postings_out';

    protected $fillable = ['adjustment_id', 'idempotency_key', 'request', 'response', 'http_status', 'succeeded', 'error'];

    protected $casts = ['request' => 'array', 'response' => 'array', 'succeeded' => 'boolean'];
}
