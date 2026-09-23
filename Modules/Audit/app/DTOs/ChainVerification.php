<?php

declare(strict_types=1);

namespace Modules\Audit\DTOs;

final readonly class ChainVerification
{
    public function __construct(
        public bool $ok,
        public int $eventsChecked,
        public ?string $headHash,
        public string $anchor,
        public ?int $brokenAtId = null,
        public ?string $reason = null,
    ) {}

    public static function broken(int $checked, ?string $head, string $anchor, ?int $id, string $reason): self
    {
        return new self(false, $checked, $head, $anchor, $id, $reason);
    }
}
