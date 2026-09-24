<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

enum SuggestionKind: string
{
    case Triage = 'triage';
    case RunSummary = 'run_summary';

    public function prompt(): string
    {
        return match ($this) {
            self::Triage => 'triage',
            self::RunSummary => 'run_summary',
        };
    }
}
