<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

enum SuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Overridden = 'overridden';
    case Informational = 'informational';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting decision',
            self::Accepted => 'Accepted',
            self::Overridden => 'Overridden',
            self::Informational => 'Informational',
            self::Failed => 'Failed',
        };
    }
}
