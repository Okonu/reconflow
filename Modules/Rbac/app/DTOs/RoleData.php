<?php

declare(strict_types=1);

namespace Modules\Rbac\DTOs;

final readonly class RoleData
{
    public function __construct(
        public string $code,
        public string $label,
        public string $description,
        public array $permissions,
    ) {}
}
