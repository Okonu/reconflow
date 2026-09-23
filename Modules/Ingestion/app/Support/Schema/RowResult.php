<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

final class RowResult
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $raw,
        public readonly array $values,
        public array $errors,
        public readonly string $recordKey,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function reject(string $reason): void
    {
        $this->errors[] = $reason;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->raw, JSON_THROW_ON_ERROR));
    }

    public function toArray(): array
    {
        return [
            'row' => $this->rowNumber,
            'key' => $this->recordKey,
            'raw' => $this->raw,
            'values' => $this->values,
            'errors' => $this->errors,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self((int) $data['row'], (array) $data['raw'], (array) $data['values'], (array) $data['errors'], (string) $data['key']);
    }
}
