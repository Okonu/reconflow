<?php

declare(strict_types=1);

namespace Modules\Users\DTOs;

final readonly class UserChanges
{
    public function __construct(
        public ?string $name = null,
        public ?string $region = null,
        public ?bool $isActive = null,
    ) {}
}
