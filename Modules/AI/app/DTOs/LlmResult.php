<?php

declare(strict_types=1);

namespace Modules\AI\DTOs;

final readonly class LlmResult
{
    public function __construct(
        public array $output,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public bool $servedByFallback = false,
    ) {}
}
