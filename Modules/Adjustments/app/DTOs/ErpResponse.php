<?php

declare(strict_types=1);

namespace Modules\Adjustments\DTOs;

final readonly class ErpResponse
{
    public function __construct(
        public bool $succeeded,
        public int $httpStatus,
        public array $body,
        public ?string $error = null,
    ) {}
}
