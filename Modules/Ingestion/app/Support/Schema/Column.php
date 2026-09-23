<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

final readonly class Column
{
    public function __construct(
        public string $name,
        public Requirement $requirement,
        public ColumnType $type,
        public string $rules,
        public string $example,
        public array $choices = [],
        public float $width = 18.0,
    ) {}

    public function formatLabel(): string
    {
        return $this->type === ColumnType::Phone ? 'Text, 12 digits' : $this->type->formatLabel();
    }

    public function isRequired(): bool
    {
        return $this->requirement === Requirement::Required;
    }
}
