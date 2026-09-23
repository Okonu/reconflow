<?php

declare(strict_types=1);

namespace Modules\Ingestion\DTOs;

final readonly class GeneratedDay
{
    public function __construct(
        public string $businessDate,
        public array $sales,
        public array $payments,
        public array $postings,
    ) {}
}
