<?php

declare(strict_types=1);

namespace Modules\Users\DTOs;

final readonly class NewUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $region,
        public array $roleIds,
    ) {}
}
