<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

final readonly class ParsedField
{
    private function __construct(
        public ?string $value,
        public ?string $error,
    ) {}

    public static function ok(string $value): self
    {
        return new self($value, null);
    }

    public static function fail(string $error): self
    {
        return new self(null, $error);
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
