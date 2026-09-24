<?php

declare(strict_types=1);

namespace Modules\Ingestion\DTOs;

use Modules\Ingestion\Models\SourceBatch;

final readonly class PullOutcome
{
    public const CREATED = 'created';

    public const UNCHANGED = 'unchanged';

    public const MANUAL_IN_EFFECT = 'manual_in_effect';

    public function __construct(
        public ?SourceBatch $batch,
        public string $result,
    ) {}

    public function created(): bool
    {
        return $this->result === self::CREATED;
    }
}
