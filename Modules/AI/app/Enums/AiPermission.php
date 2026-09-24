<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum AiPermission: string implements PermissionEnum
{
    case Use = 'ai.use';
    case Oversee = 'ai.oversee';
    case Manage = 'ai.manage';

    public function description(): string
    {
        return match ($this) {
            self::Use => 'Ask the AI assistant for triage suggestions and summaries, and accept or override them',
            self::Oversee => 'View AI usage, decisions, overrides and evaluation results',
            self::Manage => 'Turn the AI assistant on or off (kill switch)',
        };
    }

    public function group(): string
    {
        return 'AI assistant';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::Use => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
            self::Oversee => [DefaultRole::FinanceManager, DefaultRole::Auditor, DefaultRole::Administrator],
            self::Manage => [DefaultRole::FinanceManager, DefaultRole::Administrator],
        };
    }
}
