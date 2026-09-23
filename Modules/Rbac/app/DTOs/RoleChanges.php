<?php

declare(strict_types=1);

namespace Modules\Rbac\DTOs;

final readonly class RoleChanges
{
    public function __construct(
        public ?string $label = null,
        public ?string $description = null,
        public ?array $permissions = null,
    ) {}
}
