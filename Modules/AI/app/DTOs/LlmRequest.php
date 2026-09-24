<?php

declare(strict_types=1);

namespace Modules\AI\DTOs;

final readonly class LlmRequest
{
    public function __construct(
        public string $system,
        public array $context,
        public array $schema,
    ) {}

    public function userContent(): string
    {
        return json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
