<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

enum AiAuditAction: string
{
    case SuggestionCreated = 'ai.suggestion_created';
    case SuggestionFailed = 'ai.suggestion_failed';
    case SuggestionAccepted = 'ai.suggestion_accepted';
    case SuggestionOverridden = 'ai.suggestion_overridden';
    case KillSwitchToggled = 'ai.kill_switch_toggled';
    case EvalRun = 'ai.eval_run';
    case SettingsChanged = 'settings.ai_changed';
    case LogsPruned = 'ai.logs_pruned';
}
