<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

final class AiEvalRun extends Model
{
    protected $fillable = ['eval_set', 'model', 'prompt_version', 'prompt_hash', 'cases', 'action_correct', 'cause_correct', 'errors', 'results', 'triggered_by'];

    protected $casts = ['results' => 'array'];
}
