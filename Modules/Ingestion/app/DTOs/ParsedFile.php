<?php

declare(strict_types=1);

namespace Modules\Ingestion\DTOs;

final readonly class ParsedFile
{
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}
}
