<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Models;

use Illuminate\Database\Eloquent\Model;

final class ExceptionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'exception_events';

    protected $fillable = ['exception_id', 'actor_id', 'actor_label', 'type', 'from_state', 'to_state', 'comment', 'data'];

    protected $casts = ['data' => 'array'];
}
