<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Enums\SuggestionStatus;
use Modules\Users\Models\User;

final class AiSuggestion extends Model
{
    protected $fillable = [
        'kind', 'exception_id', 'run_id', 'requested_by', 'status', 'model', 'prompt_version', 'prompt_hash', 'input', 'input_hash', 'output', 'error',
        'served_by_fallback', 'latency_ms', 'input_tokens', 'output_tokens', 'decided_by', 'override_action', 'decision_reason', 'decided_at',
    ];

    protected $casts = [
        'kind' => SuggestionKind::class,
        'status' => SuggestionStatus::class,
        'input' => 'array',
        'output' => 'array',
        'served_by_fallback' => 'boolean',
        'decided_at' => 'immutable_datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
