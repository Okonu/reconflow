<?php

declare(strict_types=1);

namespace Modules\Audit\DTOs;

final readonly class ArchiveResult
{
    public function __construct(
        public int $archivedCount,
        public ?string $file = null,
        public ?string $fileSha256 = null,
        public ?int $lastArchivedId = null,
        public ?string $lastArchivedHash = null,
    ) {}
}
